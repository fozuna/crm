<?php
declare(strict_types=1);

namespace App\Services;

final class LeadPipelineNavigation
{
    public function proposalRedirectUrl(int $leadId, string $fromStage, string $toStage, string $basePath): ?string
    {
        if ($leadId <= 0) {
            return null;
        }

        if ($toStage !== LeadStages::PROPOSTA_ENVIADA || $fromStage === $toStage) {
            return null;
        }

        return rtrim($basePath, '/') . '/propostas/nova?lead_id=' . $leadId;
    }
}
