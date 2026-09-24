<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Produit un fichier de travail pour faire relire ou rédiger une traduction.
 *
 *   php artisan lang:worksheet fon
 *
 * Écrit lang/_worksheet-<locale>.csv : une ligne par chaîne, avec le texte
 * français (qui sert de clé), la traduction actuelle si elle existe, et le
 * premier endroit du code où la chaîne apparaît, pour donner le contexte.
 * À régénérer après chaque ajout de texte : le fichier n'est pas une source,
 * c'est une photo à un instant donné.
 */
class TranslationWorksheet extends Command
{
    protected $signature = 'lang:worksheet {locale : Code de la langue, ex. fon}';

    protected $description = 'Génère un fichier CSV de traduction pour un relecteur';

    private const SCANNED = ['app', 'resources/views', 'resources/js', 'routes'];

    private const PHP_FILE_PREFIXES = ['validation.', 'auth.', 'passwords.', 'pagination.', 'date.', 'badges.'];

    public function handle(): int
    {
        $locale = $this->argument('locale');

        if (! array_key_exists($locale, config('locales.supported', []))) {
            $this->error("La langue « {$locale} » n'est pas déclarée dans config/locales.php.");

            return self::FAILURE;
        }

        $existing = [];
        $jsonPath = lang_path("{$locale}.json");

        if (is_file($jsonPath)) {
            $existing = json_decode(file_get_contents($jsonPath), true) ?: [];
        }

        $strings = $this->collect();
        ksort($strings);

        $out = lang_path("_worksheet-{$locale}.csv");
        $handle = fopen($out, 'w');
        fputcsv($handle, ['francais', $locale, 'pluriel', 'vu_dans']);

        foreach ($strings as $source => $where) {
            fputcsv($handle, [
                $source,
                $existing[$source] ?? '',
                str_contains($source, '|') ? 'oui' : '',
                $where,
            ]);
        }

        fclose($handle);

        $done = count(array_filter(array_map(fn ($s) => $existing[$s] ?? '', array_keys($strings))));

        $this->info(sprintf(
            '%s : %d chaînes, dont %d déjà traduites (%d à faire).',
            "lang/_worksheet-{$locale}.csv",
            count($strings),
            $done,
            count($strings) - $done
        ));

        $this->line('Les lignes marquées « pluriel » contiennent deux formes séparées par « | ».');
        $this->line('En yoruba comme en fon, écrire les deux formes à l\'identique : ces langues');
        $this->line('n\'ont qu\'une forme de pluriel, mais Laravel en attend deux.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> texte source => "fichier:ligne" du premier usage
     */
    private function collect(): array
    {
        $pattern = '/(?:__|trans_choice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
        $found = [];

        foreach (self::SCANNED as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $lines = file($file->getPathname());

                foreach ($lines as $number => $line) {
                    preg_match_all($pattern, $line, $matches, PREG_SET_ORDER);

                    foreach ($matches as $match) {
                        $raw = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');

                        if ($raw === '') {
                            continue;
                        }

                        $key = str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], $raw);

                        foreach (self::PHP_FILE_PREFIXES as $prefix) {
                            if (str_starts_with($key, $prefix)) {
                                continue 2;
                            }
                        }

                        $found[$key] ??= str_replace(base_path().'/', '', $file->getPathname()).':'.($number + 1);
                    }
                }
            }
        }

        return $found;
    }
}
