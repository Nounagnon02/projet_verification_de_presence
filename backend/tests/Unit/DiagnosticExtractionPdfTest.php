<?php

namespace Tests\Unit;

use App\Services\Providers\GroqProvider;
use PHPUnit\Framework\TestCase;

/**
 * Diagnostic d'un echec d'extraction de texte.
 *
 * Regression : le fournisseur groq repondait « Impossible d'extraire le texte du
 * PDF » — exact, et sans usage. Ce message unique couvrait deux causes sans
 * rapport, et n'indiquait de remede pour ni l'une ni l'autre :
 *
 *  - pdftotext (paquet poppler-utils) absent de l'installation. C'etait le cas
 *    de l'image Docker de production, ou groq etait donc inutilisable sur tout
 *    document, quel qu'il soit.
 *
 *  - document scanne, sans couche texte. Cas rencontre sur une offre de
 *    formation produite par un photocopieur Samsung : deux pages, zero
 *    caractere extractible.
 *
 * Les messages sont verifies separement de leur detection : un test qui
 * dependrait de ce que la machine a d'installe ne prouverait rien.
 */
class DiagnosticExtractionPdfTest extends TestCase
{
    public function test_l_outil_manquant_nomme_le_paquet_a_installer(): void
    {
        $message = GroqProvider::messageOutilManquant();

        $this->assertStringContainsString('poppler-utils', $message);
        $this->assertStringContainsString('gemini', $message);
    }

    public function test_le_document_scanne_dit_que_c_est_une_image(): void
    {
        $message = GroqProvider::messageDocumentScanne(2);

        $this->assertStringContainsString('image', $message);
        $this->assertStringContainsString('scanner', $message);
        $this->assertStringContainsString('2 page(s)', $message);
    }

    public function test_le_document_scanne_indique_le_fournisseur_capable(): void
    {
        // Sans cette indication, l'utilisateur n'a aucun moyen de savoir que le
        // meme document passe avec un autre fournisseur.
        $message = GroqProvider::messageDocumentScanne(2);

        $this->assertStringContainsString('gemini', $message);
    }

    public function test_le_nombre_de_pages_est_facultatif(): void
    {
        // pdfinfo peut echouer la ou pdftotext a reussi a s'executer : le
        // message doit rester complet sans lui.
        $message = GroqProvider::messageDocumentScanne(null);

        $this->assertStringNotContainsString('page(s)', $message);
        $this->assertStringContainsString('scanner', $message);
    }

    public function test_les_deux_diagnostics_sont_distincts(): void
    {
        $this->assertNotSame(
            GroqProvider::messageOutilManquant(),
            GroqProvider::messageDocumentScanne(2),
            'Confondre les deux causes est precisement le defaut corrige.'
        );
    }
}
