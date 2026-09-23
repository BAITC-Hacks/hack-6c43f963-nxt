<?php

namespace App\Services;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

use function Laravel\Ai\agent;

/**
 * Maps free-text chat answers onto catalog values when AI is available.
 */
class AiContractorChatParser
{
    public function __construct(private ContractorChatGuide $guide) {}

    /**
     * @param  list<array{value: string, label: string}>  $chips
     */
    public function parse(string $step, string $raw, array $chips): ?string
    {
        $normalized = $this->guide->normalize($step, $raw);

        if ($normalized['ok']) {
            $value = $normalized['value'];

            return $value === null ? '' : (string) $value;
        }

        if (! config('contractors.ai_enabled')) {
            return null;
        }

        $provider = config('contractors.ai_provider');

        if (! config("ai.providers.{$provider}.key")) {
            return null;
        }

        try {
            $allowed = array_values(array_filter(array_column($chips, 'value'), fn (string $value): bool => $value !== ''));
            $payload = json_encode([
                'step' => $step,
                'user_text' => $raw,
                'allowed_values' => $allowed,
                'chip_labels' => $chips,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $response = agent(
                instructions: 'Ты помощник подбора подрядчиков. Сопоставь ответ пользователя с одним allowed_values. '
                    .'Если подходит «любой/пропустить/не важно» для необязательного шага, верни value пустую строку. '
                    .'Если уверенности нет, верни matched=false. Не выдумывай значения вне списка.',
                schema: fn (JsonSchema $schema): array => [
                    'matched' => $schema->boolean()->required(),
                    'value' => $schema->string()->required(),
                ],
            )->prompt($payload, provider: $provider, model: config('contractors.ai_model'), timeout: 8);

            if (! $response instanceof StructuredAgentResponse || ! ($response['matched'] ?? false)) {
                return null;
            }

            $value = (string) ($response['value'] ?? '');

            if ($value === '' && $this->guide->isOptional($step)) {
                return '';
            }

            $check = $this->guide->normalize($step, $value);

            return $check['ok'] ? ($check['value'] === null ? '' : (string) $check['value']) : null;
        } catch (Throwable $exception) {
            Log::warning('Contractor chat AI parser unavailable.', ['exception' => $exception::class]);

            return null;
        }
    }
}
