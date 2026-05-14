<?php
declare(strict_types=1);

namespace App\Services;

final class ContractPolicyService
{
    public function evaluate(array $template, array $proposal, array $items, array $client): array
    {
        $criteria = json_decode((string) ($template['auto_criteria_json'] ?? ''), true);
        $criteria = is_array($criteria) ? $criteria : [];

        if (($criteria['enabled'] ?? true) === false) {
            return [
                'eligible' => false,
                'reason' => 'Template ativo sem automacao habilitada.',
                'criteria' => $criteria,
                'matched_by' => [],
            ];
        }

        $matchedBy = [];
        $total = (float) ($proposal['total'] ?? 0);
        $minTotal = (float) ($criteria['min_total'] ?? 0);
        if ($minTotal > 0 && $total >= $minTotal) {
            $matchedBy[] = 'valor_minimo';
        }

        $clientId = (int) ($proposal['client_id'] ?? 0);
        $requiredClientIds = $this->toIntArray($criteria['required_client_ids'] ?? []);
        if (count($requiredClientIds) > 0 && in_array($clientId, $requiredClientIds, true)) {
            $matchedBy[] = 'cliente';
        }

        $itemServiceIds = [];
        $serviceKeywordsHaystack = [];
        foreach ($items as $item) {
            $sid = (int) ($item['service_id'] ?? 0);
            if ($sid > 0) {
                $itemServiceIds[] = $sid;
            }
            $serviceKeywordsHaystack[] = mb_strtolower(trim((string) ($item['description'] ?? '')));
        }

        $requiredServiceIds = $this->toIntArray($criteria['required_service_ids'] ?? []);
        if (count($requiredServiceIds) > 0 && count(array_intersect($requiredServiceIds, $itemServiceIds)) > 0) {
            $matchedBy[] = 'servico';
        }

        $keywords = $this->toStringArray($criteria['service_keywords'] ?? []);
        if (count($keywords) > 0) {
            foreach ($keywords as $keyword) {
                foreach ($serviceKeywordsHaystack as $haystack) {
                    if ($keyword !== '' && $haystack !== '' && str_contains($haystack, mb_strtolower($keyword))) {
                        $matchedBy[] = 'palavra_chave';
                        break 2;
                    }
                }
            }
        }

        $eligible = count($matchedBy) > 0;
        $reason = $eligible
            ? 'Contrato sugerido pelos criterios: ' . implode(', ', array_unique($matchedBy))
            : 'Contrato nao sugerido automaticamente para esta proposta.';

        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'criteria' => $criteria,
            'matched_by' => array_values(array_unique($matchedBy)),
            'template_id' => (int) ($template['id'] ?? 0),
            'template_name' => (string) ($template['name'] ?? ''),
            'signature_mode_default' => (string) ($template['signature_mode_default'] ?? 'print'),
            'require_signature_default' => (int) ($template['require_signature_default'] ?? 1),
            'client_name' => (string) ($client['name'] ?? ''),
        ];
    }

    private function toIntArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            $int = (int) $entry;
            if ($int > 0) {
                $out[] = $int;
            }
        }
        return array_values(array_unique($out));
    }

    private function toStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            $text = trim((string) $entry);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values(array_unique($out));
    }
}
