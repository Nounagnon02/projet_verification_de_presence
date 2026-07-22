<?php

namespace App\Contracts;

use App\ValueObjects\AnalysisResult;

interface AiProviderInterface
{
    /**
     * Analyse un document (PDF) et retourne un résultat structuré.
     *
     * @param  string $filePath Chemin absolu du fichier à analyser
     * @param  string $type     Type d'analyse (schedule|courses)
     * @return AnalysisResult
     */
    public function analyzeDocument(string $filePath, string $type): AnalysisResult;

    /**
     * Retourne le nom du provider (ex: gemini, groq, openrouter).
     */
    public function getName(): string;
}
