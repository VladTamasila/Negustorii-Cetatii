<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PartidaRepository;
use SoapFault;

/**
 * SoapService - protocol alternativ (SOAP) peste aceleasi date.
 *
 * Pe langa API-ul REST/JSON, expunem cateva operatii si prin SOAP, ca sa
 * demonstram un protocol alternativ. Metodele publice de aici devin automat
 * operatiile SOAP (vezi SoapController).
 */
final class SoapService
{
    public function __construct(private readonly PartidaRepository $partide) {}

    /** Numarul total de partide. */
    public function numarPartide(): int
    {
        return $this->partide->findAll(null, 1, 1)['total'];
    }

    /** Lista partidelor (maxim 100). Intoarce un array de structuri. */
    public function listaPartide(): array
    {
        return $this->partide->findAll(null, 1, 100)['items'];
    }

    /** Detaliile unei partide dupa id. Arunca SoapFault daca nu exista. */
    public function detaliiPartida(int $id): array
    {
        $p = $this->partide->findById($id);
        if ($p === null) {
            throw new SoapFault('Client', "Partida $id nu exista.");
        }
        return [
            'id'     => (int) $p['id'],
            'nume'   => $p['nume'],
            'status' => $p['status'],
            'faza'   => $p['faza'],
        ];
    }
}
