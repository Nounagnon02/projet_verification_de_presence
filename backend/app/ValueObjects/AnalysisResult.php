<?php

namespace App\ValueObjects;

/**
 * Value Object représentant le résultat d'une analyse IA.
 * Immuable — toutes les propriétés sont en readonly.
 */
class AnalysisResult
{
    /**
     * @param string      $status       completed | failed
     * @param array|null  $data         Données parsées (events, ues, ecs, etc.)
     * @param string|null $errorMessage Message d'erreur si status = failed
     * @param float|null  $confidence   Score de confiance (0.0 - 1.0)
     * @param string|null $warning      Avertissement éventuel
     * @param array       $metadata     Métadonnées additionnelles (filename, counts, etc.)
     */
    public function __construct(
        public readonly string  $status,
        public readonly ?array  $data = null,
        public readonly ?string $errorMessage = null,
        public readonly ?float  $confidence = null,
        public readonly ?string $warning = null,
        public readonly array   $metadata = [],
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function requiresValidation(): bool
    {
        return $this->confidence !== null && $this->confidence < 0.70;
    }

    /**
     * Crée une instance d'échec.
     */
    public static function failed(string $message): self
    {
        return new self(
            status: 'failed',
            errorMessage: $message,
        );
    }

    /**
     * Crée une instance de succès.
     */
    public static function completed(
        array   $data,
        ?float  $confidence = null,
        ?string $warning = null,
        array   $metadata = [],
    ): self {
        return new self(
            status: 'completed',
            data: $data,
            confidence: $confidence,
            warning: $warning,
            metadata: $metadata,
        );
    }
}
