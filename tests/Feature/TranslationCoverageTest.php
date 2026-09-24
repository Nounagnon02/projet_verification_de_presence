<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Garde-fou : toute chaîne passée à __() ou trans_choice() doit exister dans
 * chaque langue traduite. Le français est la langue source : il n'a pas de
 * fichier JSON, la clé EST le texte français.
 */
class TranslationCoverageTest extends TestCase
{
    /**
     * Langues qui doivent couvrir 100 % des clés.
     *
     * Le yoruba et le fon ne sont pas listés tant qu'un locuteur n'a pas relu
     * leur traduction : sans fichier, ils retombent sur le français
     * (APP_FALLBACK_LOCALE=fr), ce qui vaut mieux qu'une traduction inventée.
     */
    private const TRANSLATED_LOCALES = ['en', 'es'];

    /**
     * Répertoires balayés à la recherche d'appels de traduction.
     */
    private const SCANNED = ['app', 'resources/views', 'resources/js', 'routes'];

    /**
     * Préfixes qui vivent dans des fichiers PHP (lang/<locale>/<fichier>.php)
     * et non dans le JSON.
     */
    private const PHP_FILE_PREFIXES = ['validation.', 'auth.', 'passwords.', 'pagination.', 'date.', 'badges.'];

    public function test_chaque_chaine_traduite_existe_dans_toutes_les_langues(): void
    {
        $keys = $this->collectKeys();

        $this->assertNotEmpty($keys, 'Aucune clé de traduction trouvée : le scanner est probablement cassé.');

        foreach (self::TRANSLATED_LOCALES as $locale) {
            $path = lang_path("{$locale}.json");
            $this->assertFileExists($path, "Fichier de traduction manquant : lang/{$locale}.json");

            $translations = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            $missing = array_values(array_diff($keys, array_keys($translations)));
            $unused = array_values(array_diff(array_keys($translations), $keys));

            $this->assertSame([], $missing, sprintf(
                "%d clé(s) absente(s) de lang/%s.json :\n  - %s",
                count($missing),
                $locale,
                implode("\n  - ", $missing)
            ));

            $this->assertSame([], $unused, sprintf(
                "%d clé(s) de lang/%s.json ne sont plus utilisées :\n  - %s",
                count($unused),
                $locale,
                implode("\n  - ", $unused)
            ));
        }
    }

    public function test_les_formes_plurielles_ont_le_meme_nombre_de_segments(): void
    {
        foreach (self::TRANSLATED_LOCALES as $locale) {
            $translations = json_decode(file_get_contents(lang_path("{$locale}.json")), true, 512, JSON_THROW_ON_ERROR);

            foreach ($translations as $source => $translated) {
                if (! str_contains($source, '|')) {
                    continue;
                }

                $this->assertSame(
                    substr_count($source, '|'),
                    substr_count($translated, '|'),
                    "Nombre de formes plurielles différent en {$locale} pour « {$source} »."
                );
            }
        }
    }

    /**
     * @return list<string> les littéraux passés à __() / trans_choice()
     */
    private function collectKeys(): array
    {
        $pattern = '/(?:__|trans_choice)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
        $keys = [];

        foreach (self::SCANNED as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                preg_match_all($pattern, file_get_contents($file->getPathname()), $matches, PREG_SET_ORDER);

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

                    $keys[$key] = true;
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }
}
