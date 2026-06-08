<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Template - un mic motor de template-ing (in stilul Mustache/Handlebars).
 *
 * Suporta:
 *   - variabile simple:      {{ nume }}            sau imbricate {{ partida.nume }}
 *   - bucle:                 {{#each lista}} ... {{ camp }} ... {{/each}}
 *
 * Toate valorile sunt escapate automat (htmlspecialchars), ca sa nu avem
 * probleme de tip XSS. Il folosim ca sa generam pagini HTML din date (vezi
 * RaportController) - adica template-ing integrat in operatiile REST.
 */
final class Template
{
    public function __construct(private readonly string $directorTemplate) {}

    /** Randeaza un fisier template cu datele primite si intoarce HTML-ul. */
    public function randeaza(string $numeFisier, array $date): string
    {
        $cale = $this->directorTemplate . '/' . $numeFisier;
        $continut = @file_get_contents($cale);
        if ($continut === false) {
            throw new RuntimeException("Template-ul '$numeFisier' nu a fost gasit.");
        }
        return $this->proceseaza($continut, $date);
    }

    private function proceseaza(string $tpl, array $date): string
    {
        // 1) Intai rezolvam buclele: {{#each cheie}} ... {{/each}}
        $tpl = preg_replace_callback(
            '/\{\{#each\s+([\w.]+)\}\}(.*?)\{\{\/each\}\}/s',
            function (array $m) use ($date): string {
                $lista = $this->valoare($date, $m[1]);
                if (!is_array($lista)) return '';
                $rezultat = '';
                foreach ($lista as $element) {
                    $context = is_array($element) ? $element : ['_' => $element];
                    $rezultat .= $this->inlocuiesteVariabile($m[2], $context);
                }
                return $rezultat;
            },
            $tpl
        );

        // 2) Apoi variabilele simple de la nivelul de baza
        return $this->inlocuiesteVariabile($tpl, $date);
    }

    private function inlocuiesteVariabile(string $tpl, array $date): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            function (array $m) use ($date): string {
                $val = $this->valoare($date, $m[1]);
                if (is_array($val) || $val === null) return '';
                return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
            },
            $tpl
        );
    }

    /** Cauta o valoare dupa o cale de tip "a.b.c" intr-un array imbricat. */
    private function valoare(array $date, string $cale): mixed
    {
        $val = $date;
        foreach (explode('.', $cale) as $parte) {
            if (is_array($val) && array_key_exists($parte, $val)) {
                $val = $val[$parte];
            } else {
                return null;
            }
        }
        return $val;
    }
}
