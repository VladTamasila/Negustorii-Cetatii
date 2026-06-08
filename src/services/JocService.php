<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\AsezareRepository;
use App\Repositories\DrumRepository;
use App\Repositories\HexagonRepository;
use App\Repositories\JucatorRepository;
use App\Repositories\MuchieRepository;
use App\Repositories\MutareRepository;
use App\Repositories\PartidaRepository;
use App\Repositories\VarfRepository;
use RuntimeException;

/**
 * JocService - logica de joc pentru Negustorii Cetatii (Catan-like)
 *
 * Reguli:
 *   - Harta: 19 hexagoane in stil Catan, 5 tipuri de teren + desert.
 *   - Zaruri: 2 zaruri, suma 2..12 (fara 7 ca productie). Pentru fiecare
 *     hexagon cu acel numar, fiecare jucator cu o asezare la unul din
 *     varfurile hexagonului primeste 1 resursa (cetatea = 2 resurse).
 *
 *   - Faza "asezare_initiala": fiecare jucator pune 2 asezari + 2 drumuri,
 *     in ordine snake draft (1->2->3->4 prima runda, 4->3->2->1 a doua).
 *     Dupa a doua asezare, primeste resurse de start - cate 1 din fiecare
 *     teren adiacent.
 *
 *   - Costuri:
 *     drum    = 1 lemn + 1 piatra
 *     asezare = 1 lemn + 1 piatra + 1 grau + 1 lana
 *     cetate  = 2 aur  + 3 grau (upgrade pe asezare existenta)
 *
 *   - Prestigiu: asezare=1, cetate=2. Upgrade adauga +1.
 *   - Castig: primul care ajunge la `punctaj_castig` (implicit 10).
 *
 *   - Reguli geometrice:
 *     * Asezarea: varf liber + nu exista alta asezare pe varfurile vecine
 *       (regula distantei 2). In faza normala, plus: trebuie sa fie atinsa
 *       de un drum propriu.
 *     * Drumul:   muchie libera + are conexiune (drum sau asezare proprie)
 *       la unul din varfuri.
 *     * Cetatea:  upgradezi o asezare proprie deja plasata.
 *
 *   - Fara hot/atac/furt - varianta peaceful.
 */
final class JocService
{
    /** Costuri pentru constructii (in resurse). */
    private const COST_DRUM    = ['lemn' => 1, 'piatra' => 1, 'aur' => 0, 'grau' => 0, 'lana' => 0];
    private const COST_ASEZARE = ['lemn' => 1, 'piatra' => 1, 'aur' => 0, 'grau' => 1, 'lana' => 1];
    private const COST_CETATE  = ['lemn' => 0, 'piatra' => 0, 'aur' => 2, 'grau' => 3, 'lana' => 0];

    /** Maparea terrain -> resursa produsa. */
    private const TERRAIN_RESURSA = [
        'padure' => 'lemn',
        'deal'   => 'piatra',
        'munte'  => 'aur',
        'camp'   => 'grau',
        'pasune' => 'lana',
        'desert' => null,
    ];

    public function __construct(
        private readonly PartidaRepository $partide,
        private readonly JucatorRepository $jucatori,
        private readonly MutareRepository  $mutari,
        private readonly HexagonRepository $hexagoane,
        private readonly VarfRepository    $varfuri,
        private readonly MuchieRepository  $muchii,
        private readonly AsezareRepository $asezari,
        private readonly DrumRepository    $drumuri,
        private readonly HartaService      $harta,
    ) {
    }

    // =================================================================
    // PORNIRE PARTIDA
    // =================================================================

    /**
     * Genereaza harta, treci in faza 'asezare_initiala', primul jucator activ.
     */
    public function pornestePartida(int $idPartida): array
    {
        $partida = $this->partidaSauEroare($idPartida);

        if ($partida['faza'] !== 'in_asteptare') {
            throw new RuntimeException("Partida nu mai poate fi pornita (faza: {$partida['faza']}).");
        }

        $jucatori = $this->jucatori->findByPartida($idPartida);
        if (count($jucatori) < 2) {
            throw new RuntimeException('Partida are nevoie de minim 2 jucatori.');
        }

        // 1. Genereaza harta (hexagoane, varfuri, muchii)
        $this->harta->genereaza($idPartida);

        // 2. Primul jucator dupa ordine intra in faza de asezare initiala
        $primul = $jucatori[0];
        $this->partide->pornesteSetup($idPartida, (int) $primul['id']);

        // Notificare async: partida a pornit, incepe asezarea initiala.
        $this->mutari->create($idPartida, (int) $primul['id'], 'start', [],
            'Partida a pornit! Incepe asezarea initiala.', 0);

        return $this->partide->findById($idPartida) ?? [];
    }

    // =================================================================
    // FAZA SETUP - asezare + drum initial
    // =================================================================

    /**
     * Plaseaza asezarea initiala a jucatorului activ, urmata automat de
     * trecerea pasului la "drum".
     */
    public function aseazaInitiala(int $idPartida, int $idVarf): array
    {
        $partida = $this->partidaSauEroare($idPartida);
        if ($partida['faza'] !== 'asezare_initiala') {
            throw new RuntimeException('Partida nu este in faza de asezare initiala.');
        }
        if ($partida['pas_setup'] !== 'asezare') {
            throw new RuntimeException('Asteptam un drum, nu o asezare.');
        }

        $jucator = $this->jucatorActivSauEroare($partida);
        $this->valideazaAsezareGeometric($idPartida, $idVarf);

        $this->asezari->create($idPartida, (int) $jucator['id'], $idVarf, 'asezare');

        // Prestigiu +1
        $resurseNoi = $this->resurseJucator($jucator);
        $resurseNoi['prestigiu'] += 1;

        // In runda 2: primeste resurse de start (cate 1 din fiecare teren adiacent)
        $resurseStart = ['lemn'=>0,'piatra'=>0,'aur'=>0,'grau'=>0,'lana'=>0];
        if ((int) $partida['runda_setup'] === 2) {
            $hexagoane = $this->varfuri->hexagoaneAdiacente($idVarf);
            foreach ($hexagoane as $h) {
                $resursa = self::TERRAIN_RESURSA[$h['terrain']];
                if ($resursa !== null) {
                    $resurseNoi[$resursa] += 1;
                    $resurseStart[$resursa] += 1;
                }
            }
        }

        $this->jucatori->setResurse((int) $jucator['id'], $resurseNoi);

        $payload = [
            'idVarf'       => $idVarf,
            'rundaSetup'   => (int) $partida['runda_setup'],
            'resurseStart' => $resurseStart,
        ];
        $mesaj = sprintf(
            '%s a plasat asezarea initiala (runda %d).',
            $jucator['nume'], $partida['runda_setup']
        );
        $this->mutari->create($idPartida, (int) $jucator['id'], 'asezare', $payload, $mesaj, 0);

        // Trecem la pasul "drum" - jucatorul trebuie sa puna un drum legat de asezare
        $this->partide->setPasSetup($idPartida, 'drum');

        return ['mesaj' => $mesaj, 'pasUrmator' => 'drum', 'resurseStart' => $resurseStart];
    }

    /**
     * Plaseaza drumul initial al jucatorului activ. Drumul trebuie sa atinga
     * varful pe care tocmai a fost plasata asezarea (ultima asezare a sa).
     */
    public function construiesteDrumInitial(int $idPartida, int $idMuchie): array
    {
        $partida = $this->partidaSauEroare($idPartida);
        if ($partida['faza'] !== 'asezare_initiala') {
            throw new RuntimeException('Partida nu este in faza de asezare initiala.');
        }
        if ($partida['pas_setup'] !== 'drum') {
            throw new RuntimeException('Asteptam o asezare, nu un drum.');
        }
        $jucator = $this->jucatorActivSauEroare($partida);

        $muchie = $this->muchii->findById($idMuchie);
        if ($muchie === null || (int) $muchie['partida_id'] !== $idPartida) {
            throw new RuntimeException('Muchia nu apartine acestei partide.');
        }
        if ($this->drumuri->findByMuchie($idPartida, $idMuchie) !== null) {
            throw new RuntimeException('Aceasta muchie are deja un drum.');
        }

        // Drumul trebuie sa atinga ultima asezare (orice asezare, in setup)
        // Implementare simpla: drumul trebuie sa atinga macar o asezare proprie.
        $atingeAsezare = false;
        foreach ([(int) $muchie['varf_a_id'], (int) $muchie['varf_b_id']] as $idV) {
            $a = $this->asezari->findByVarf($idPartida, $idV);
            if ($a !== null && (int) $a['jucator_id'] === (int) $jucator['id']) {
                $atingeAsezare = true;
                break;
            }
        }
        if (!$atingeAsezare) {
            throw new RuntimeException('Drumul initial trebuie sa porneasca de la o asezare proprie.');
        }

        $this->drumuri->create($idPartida, (int) $jucator['id'], $idMuchie);

        $mesaj = sprintf('%s a plasat drumul initial.', $jucator['nume']);
        $this->mutari->create($idPartida, (int) $jucator['id'], 'drum',
            ['idMuchie' => $idMuchie, 'rundaSetup' => (int) $partida['runda_setup']],
            $mesaj, 0
        );

        // Avansam in snake draft
        $this->avanseazaSetup($idPartida);

        return ['mesaj' => $mesaj];
    }

    /**
     * Avanseaza state machine-ul de setup conform regulilor snake draft.
     */
    private function avanseazaSetup(int $idPartida): void
    {
        $partida = $this->partidaSauEroare($idPartida);
        $jucatori = $this->jucatori->findByPartida($idPartida); // sortati dupa ordine
        $idCurent = (int) $partida['jucator_activ_id'];

        // Gasim pozitia jucatorului curent in lista
        $pozCurent = null;
        foreach ($jucatori as $i => $j) {
            if ((int) $j['id'] === $idCurent) { $pozCurent = $i; break; }
        }
        $n = count($jucatori);

        $rundaSetup = (int) $partida['runda_setup'];

        if ($rundaSetup === 1) {
            // Mergem inainte: 0, 1, 2, ..., n-1, apoi schimbam runda
            if ($pozCurent < $n - 1) {
                $idUrmator = (int) $jucatori[$pozCurent + 1]['id'];
                $this->partide->actualizeazaSetup($idPartida, $idUrmator, 1, 'asezare');
            } else {
                // Trecem in runda 2 - acelasi jucator joaca a doua oara (snake)
                $this->partide->actualizeazaSetup($idPartida, $idCurent, 2, 'asezare');
            }
        } else {
            // Runda 2: mergem invers: n-1, n-2, ..., 0
            if ($pozCurent > 0) {
                $idUrmator = (int) $jucatori[$pozCurent - 1]['id'];
                $this->partide->actualizeazaSetup($idPartida, $idUrmator, 2, 'asezare');
            } else {
                // S-a terminat setup-ul - intram in faza de joc.
                $this->partide->intraInJoc($idPartida, (int) $jucatori[0]['id']);
            }
        }
    }

    // =================================================================
    // FAZA DE JOC NORMALA
    // =================================================================

    /**
     * Aruncarea zarului. Distribuie resurse tuturor jucatorilor cu asezari/cetati
     * pe varfurile hexagoanelor cu numarul rezultat.
     */
    public function aruncaZarul(int $idPartida): array
    {
        $partida = $this->partidaInJocSauEroare($idPartida);
        $jucator = $this->jucatorActivSauEroare($partida);

        if ($this->mutari->zarAruncatInTura($idPartida, (int) $jucator['id'], (int) $partida['tura_curenta'])) {
            throw new RuntimeException('Ai aruncat deja zarul in aceasta tura. Construieste sau paseaza.');
        }

        $zar1 = random_int(1, 6);
        $zar2 = random_int(1, 6);
        $suma = $zar1 + $zar2;

        // 7 = nimic (in Catan ar fi hot, dar noi nu il avem)
        $productie = []; // [jucatorId => [resursa => cantitate]]

        if ($suma !== 7) {
            $hexagoane = $this->hexagoane->findByNumar($idPartida, $suma);
            foreach ($hexagoane as $h) {
                $resursa = self::TERRAIN_RESURSA[$h['terrain']];
                if ($resursa === null) continue;

                $idsVarfuri = $this->hexagoane->varfuriHexagon((int) $h['id']);
                foreach ($idsVarfuri as $idV) {
                    $a = $this->asezari->findByVarf($idPartida, $idV);
                    if ($a === null) continue;
                    $cantitate = $a['tip'] === 'cetate' ? 2 : 1;
                    $productie[(int) $a['jucator_id']][$resursa] =
                        ($productie[(int) $a['jucator_id']][$resursa] ?? 0) + $cantitate;
                }
            }

            // Aplicam productia
            foreach ($productie as $idJ => $deltaResurse) {
                $j = $this->jucatori->findById($idJ);
                $r = $this->resurseJucator($j);
                foreach ($deltaResurse as $res => $cant) {
                    $r[$res] += $cant;
                }
                $this->jucatori->setResurse($idJ, $r);
            }
        }

        $payload = [
            'zar1'      => $zar1,
            'zar2'      => $zar2,
            'suma'      => $suma,
            'productie' => $productie,
        ];
        $mesaj = $suma === 7
            ? sprintf('%s a aruncat 7 - nu produce nimic.', $jucator['nume'])
            : sprintf('%s a aruncat %d + %d = %d. %s', $jucator['nume'], $zar1, $zar2, $suma,
                empty($productie) ? 'Nimeni nu a colectat resurse.' : $this->descrieProductie($productie));

        $this->mutari->create($idPartida, (int) $jucator['id'], 'zar', $payload, $mesaj,
            (int) $partida['tura_curenta']);

        return $payload + ['mesaj' => $mesaj];
    }

    /**
     * Construieste o asezare in faza normala. Necesita conexiune la drum propriu.
     */
    public function construiesteAsezare(int $idPartida, int $idVarf): array
    {
        $partida = $this->partidaInJocSauEroare($idPartida);
        $jucator = $this->jucatorActivSauEroare($partida);

        $this->valideazaAsezareGeometric($idPartida, $idVarf);

        // Trebuie sa aiba drum propriu care atinge varful
        if (!$this->drumuri->jucatorAreDrumLaVarf($idPartida, (int) $jucator['id'], $idVarf)) {
            throw new RuntimeException('Asezarea trebuie sa fie pe un varf atins de un drum propriu.');
        }

        $resurse = $this->resurseJucator($jucator);
        $this->verificaResurse($resurse, self::COST_ASEZARE, 'asezare');

        $resurse = $this->scadeCost($resurse, self::COST_ASEZARE);
        $resurse['prestigiu'] += 1;
        $this->jucatori->setResurse((int) $jucator['id'], $resurse);

        $idAsezare = $this->asezari->create($idPartida, (int) $jucator['id'], $idVarf, 'asezare');

        $mesaj = sprintf('%s a construit o asezare.', $jucator['nume']);
        $this->mutari->create($idPartida, (int) $jucator['id'], 'asezare',
            ['idAsezare' => $idAsezare, 'idVarf' => $idVarf], $mesaj,
            (int) $partida['tura_curenta']
        );

        $this->verificaCastig($idPartida, (int) $jucator['id'], $resurse['prestigiu'],
            (int) $partida['punctaj_castig']);

        return ['mesaj' => $mesaj, 'idAsezare' => $idAsezare];
    }

    /**
     * Construieste un drum in faza normala. Necesita conexiune la drum sau
     * asezare proprie pe unul din varfurile muchiei.
     */
    public function construiesteDrum(int $idPartida, int $idMuchie): array
    {
        $partida = $this->partidaInJocSauEroare($idPartida);
        $jucator = $this->jucatorActivSauEroare($partida);

        $muchie = $this->muchii->findById($idMuchie);
        if ($muchie === null || (int) $muchie['partida_id'] !== $idPartida) {
            throw new RuntimeException('Muchia nu apartine acestei partide.');
        }
        if ($this->drumuri->findByMuchie($idPartida, $idMuchie) !== null) {
            throw new RuntimeException('Aceasta muchie are deja un drum.');
        }
        if (!$this->drumuri->jucatorAreConexiuneLaMuchie($idPartida, (int) $jucator['id'], $idMuchie)) {
            throw new RuntimeException('Drumul trebuie sa fie conectat la un drum sau o asezare proprie.');
        }

        $resurse = $this->resurseJucator($jucator);
        $this->verificaResurse($resurse, self::COST_DRUM, 'drum');

        $resurse = $this->scadeCost($resurse, self::COST_DRUM);
        $this->jucatori->setResurse((int) $jucator['id'], $resurse);

        $idDrum = $this->drumuri->create($idPartida, (int) $jucator['id'], $idMuchie);

        $mesaj = sprintf('%s a construit un drum.', $jucator['nume']);
        $this->mutari->create($idPartida, (int) $jucator['id'], 'drum',
            ['idDrum' => $idDrum, 'idMuchie' => $idMuchie], $mesaj,
            (int) $partida['tura_curenta']
        );

        return ['mesaj' => $mesaj, 'idDrum' => $idDrum];
    }

    /**
     * Upgrade asezare -> cetate. Costa 2 aur + 3 grau, +1 prestigiu (asezare = 1, cetate = 2).
     */
    public function upgradeCetate(int $idPartida, int $idAsezare): array
    {
        $partida = $this->partidaInJocSauEroare($idPartida);
        $jucator = $this->jucatorActivSauEroare($partida);

        // Trebuie sa fie a jucatorului activ si de tip 'asezare'
        $rows = $this->asezari->findByJucator((int) $jucator['id']);
        $tinta = null;
        foreach ($rows as $r) {
            if ((int) $r['id'] === $idAsezare && $r['tip'] === 'asezare') {
                $tinta = $r;
                break;
            }
        }
        if ($tinta === null) {
            throw new RuntimeException('Asezarea nu exista, nu este a ta sau e deja cetate.');
        }

        $resurse = $this->resurseJucator($jucator);
        $this->verificaResurse($resurse, self::COST_CETATE, 'cetate');

        $resurse = $this->scadeCost($resurse, self::COST_CETATE);
        $resurse['prestigiu'] += 1; // asezare era +1, cetatea e +2 -> diferenta +1
        $this->jucatori->setResurse((int) $jucator['id'], $resurse);

        $this->asezari->updateTip($idAsezare, 'cetate');

        $mesaj = sprintf('%s a transformat o asezare in cetate.', $jucator['nume']);
        $this->mutari->create($idPartida, (int) $jucator['id'], 'cetate',
            ['idAsezare' => $idAsezare], $mesaj,
            (int) $partida['tura_curenta']
        );

        $this->verificaCastig($idPartida, (int) $jucator['id'], $resurse['prestigiu'],
            (int) $partida['punctaj_castig']);

        return ['mesaj' => $mesaj];
    }

    /**
     * Pasarea turei in faza normala.
     */
    public function paseaza(int $idPartida): array
    {
        $partida = $this->partidaInJocSauEroare($idPartida);
        $jucator = $this->jucatorActivSauEroare($partida);

        if (!$this->mutari->zarAruncatInTura($idPartida, (int) $jucator['id'], (int) $partida['tura_curenta'])) {
            throw new RuntimeException('Trebuie sa arunci zarul inainte sa pasezi.');
        }

        $jucatori = $this->jucatori->findByPartida($idPartida);
        $ids = array_map(static fn(array $j): int => (int) $j['id'], $jucatori);
        $poz = array_search((int) $jucator['id'], $ids, true);
        $pozUrmator = ($poz + 1) % count($ids);
        $idUrmator = $ids[$pozUrmator];

        $turaNoua = (int) $partida['tura_curenta'];
        if ($pozUrmator === 0) $turaNoua += 1;

        $this->partide->avanseazaTura($idPartida, $idUrmator, $turaNoua);

        $mesaj = sprintf('%s a pasat tura.', $jucator['nume']);
        $this->mutari->create($idPartida, (int) $jucator['id'], 'paseaza', [], $mesaj, $turaNoua);

        return ['mesaj' => $mesaj, 'jucatorActivId' => $idUrmator, 'turaCurenta' => $turaNoua];
    }

    // =================================================================
    // VALIDARI SI HELPERI PRIVATI
    // =================================================================

    /**
     * Regula geometrica a asezarilor:
     * - varful trebuie sa apartina partidei
     * - varful trebuie sa fie liber
     * - niciun varf adiacent (la 1 muchie distanta) nu are deja asezare
     */
    private function valideazaAsezareGeometric(int $idPartida, int $idVarf): void
    {
        $varf = $this->varfuri->findById($idVarf);
        if ($varf === null || (int) $varf['partida_id'] !== $idPartida) {
            throw new RuntimeException('Varful nu apartine acestei partide.');
        }
        if ($this->asezari->findByVarf($idPartida, $idVarf) !== null) {
            throw new RuntimeException('Acest varf este deja ocupat.');
        }
        // Regula distantei
        $vecini = $this->varfuri->varfuriAdiacente($idVarf);
        foreach ($vecini as $idVecin) {
            if ($this->asezari->findByVarf($idPartida, $idVecin) !== null) {
                throw new RuntimeException('Asezarile nu pot fi adiacente (regula distantei 2).');
            }
        }
    }

    /**
     * @param array{lemn:int,piatra:int,aur:int,grau:int,lana:int,prestigiu:int} $resurse
     * @param array{lemn:int,piatra:int,aur:int,grau:int,lana:int}               $cost
     */
    private function verificaResurse(array $resurse, array $cost, string $cumpara): void
    {
        foreach (['lemn','piatra','aur','grau','lana'] as $r) {
            if ($resurse[$r] < $cost[$r]) {
                throw new RuntimeException(sprintf(
                    'Resurse insuficiente pentru %s. Cost: %s. Ai: L%d P%d A%d G%d La%d.',
                    $cumpara, $this->descriereCost($cost),
                    $resurse['lemn'], $resurse['piatra'], $resurse['aur'],
                    $resurse['grau'], $resurse['lana']
                ));
            }
        }
    }

    private function scadeCost(array $resurse, array $cost): array
    {
        foreach (['lemn','piatra','aur','grau','lana'] as $r) {
            $resurse[$r] -= $cost[$r];
        }
        return $resurse;
    }

    private function descriereCost(array $c): string
    {
        $b = [];
        if ($c['lemn']   > 0) $b[] = $c['lemn']   . ' lemn';
        if ($c['piatra'] > 0) $b[] = $c['piatra'] . ' piatra';
        if ($c['aur']    > 0) $b[] = $c['aur']    . ' aur';
        if ($c['grau']   > 0) $b[] = $c['grau']   . ' grau';
        if ($c['lana']   > 0) $b[] = $c['lana']   . ' lana';
        return implode(' + ', $b);
    }

    private function descrieProductie(array $productie): string
    {
        $bucati = [];
        foreach ($productie as $idJ => $resurse) {
            $j = $this->jucatori->findById($idJ);
            $detalii = [];
            foreach ($resurse as $r => $c) $detalii[] = $c . ' ' . $r;
            $bucati[] = $j['nume'] . ': ' . implode(', ', $detalii);
        }
        return implode(' | ', $bucati);
    }

    private function verificaCastig(int $idPartida, int $idJucator, int $prestigiu, int $prag): void
    {
        if ($prestigiu >= $prag) {
            $this->partide->finalizeaza($idPartida, $idJucator);

            // Notificare async: anunta toti jucatorii ca partida s-a terminat.
            $invingator = $this->jucatori->findById($idJucator);
            $this->mutari->create($idPartida, $idJucator, 'castig', ['prestigiu' => $prestigiu],
                sprintf('%s a castigat partida cu %d prestigiu!',
                    $invingator['nume'] ?? 'Jucatorul', $prestigiu),
                0
            );
        }
    }

    /**
     * @return array{lemn:int,piatra:int,aur:int,grau:int,lana:int,prestigiu:int}
     */
    private function resurseJucator(array $j): array
    {
        return [
            'lemn'      => (int) $j['lemn'],
            'piatra'    => (int) $j['piatra'],
            'aur'       => (int) $j['aur'],
            'grau'      => (int) $j['grau'],
            'lana'      => (int) $j['lana'],
            'prestigiu' => (int) $j['prestigiu'],
        ];
    }

    private function partidaSauEroare(int $idPartida): array
    {
        $p = $this->partide->findById($idPartida);
        if ($p === null) throw new RuntimeException("Partida {$idPartida} nu exista.");
        return $p;
    }

    private function partidaInJocSauEroare(int $idPartida): array
    {
        $p = $this->partidaSauEroare($idPartida);
        if ($p['faza'] !== 'joc') {
            throw new RuntimeException("Partida nu este in faza de joc (faza: {$p['faza']}).");
        }
        return $p;
    }

    private function jucatorActivSauEroare(array $partida): array
    {
        if ($partida['jucator_activ_id'] === null) {
            throw new RuntimeException('Nu exista jucator activ.');
        }
        $j = $this->jucatori->findById((int) $partida['jucator_activ_id']);
        if ($j === null) throw new RuntimeException('Jucatorul activ nu mai exista.');
        return $j;
    }
}
