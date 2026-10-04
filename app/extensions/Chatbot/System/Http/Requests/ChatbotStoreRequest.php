<?php

namespace App\Extensions\Chatbot\System\Http\Requests;

use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\Chatbot\System\Models\ChatbotAvatar;
use App\Helpers\Classes\Helper;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatbotStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'uuid'                          => ['required', 'string'],
            'user_id'                       => ['required', 'integer', 'exists:users,id'],
            'title'                         => ['required', 'string'],
            'bubble_message'                => ['required', 'string'],
            'welcome_message'               => ['required', 'string'],
            'interaction_type'              => ['required', 'string'],
            'instructions'                  => Helper::appIsNotDemo() ? ['required', 'string'] : ['sometimes', 'nullable', 'string'],
            'do_not_go_beyond_instructions' => ['required', 'boolean'],
            'suggested_prompts'             => ['sometimes', 'nullable', 'array'],
            'suggested_prompts.*.name'      => ['sometimes', 'nullable', 'string'],
            'suggested_prompts.*.prompt'    => ['sometimes', 'nullable', 'string'],
            'suggested_prompts_enabled'     => ['sometimes', 'boolean'],
            'language'                      => ['sometimes', 'nullable', 'string'],
            'ai_model'                      => ['required', 'string'],
            'ai_embedding_model'            => ['required', 'string'],
            'avatar'                        => ['nullable', 'sometimes'],
            'human_agent_conditions'        => ['sometimes', 'nullable', 'array'],
            'is_booking_assistant'          => ['sometimes', 'boolean'],
            'booking_assistant_conditions'  => ['sometimes', 'nullable', 'array'],
            'booking_assistant_iframe'      => ['sometimes', 'nullable', 'string'],
            'voice_call_enabled'            => ['sometimes', 'boolean'],
            'voice_call_first_message'      => ['nullable', 'string'],
            'trusted_domains'               => ['sometimes', 'nullable', 'array'],
            'is_review_enabled'             => ['sometimes', 'nullable', 'boolean'],
            'review_prompt'                 => ['sometimes', 'nullable', 'string'],
            'review_responses'              => ['sometimes', 'nullable', 'array', 'max:5'],
            'review_responses.*'            => ['nullable', 'string'],
            'is_shop'                       => ['sometimes', 'boolean'],
            'shop_source'                   => ['sometimes', 'nullable', 'string'],
            'shop_features'                 => ['sometimes', 'nullable', 'array'],
            'shopify_domain'                => ['sometimes', 'nullable', 'string'],
            'shopify_access_token'          => ['sometimes', 'nullable', 'string'],
            'woocommerce_domain'            => ['sometimes', 'nullable', 'string'],
            'woocommerce_consumer_key'      => ['sometimes', 'nullable', 'string'],
            'woocommerce_consumer_secret'   => ['sometimes', 'nullable', 'string'],
            'titan_template'                => ['sometimes', 'nullable', 'string', 'max:80'],
            'shell_builder_config'          => ['sometimes', 'nullable', 'array'],
            'shell_builder_config.device'   => ['sometimes', 'string', Rule::in(['mobile', 'tablet', 'desktop'])],
            'shell_builder_config.role'     => ['sometimes', 'string', 'max:80'],
            'shell_builder_config.state'    => ['sometimes', 'string', Rule::in(['online', 'offline', 'syncing', 'conflict', 'empty', 'populated'])],
            'shell_builder_config.theme'    => ['sometimes', 'string', Rule::in(['light', 'dark', 'system'])],
            'shell_builder_config.primary'  => ['sometimes', 'array', 'max:6'],
            'shell_builder_config.primary.*.id' => ['required_with:shell_builder_config.primary', 'string', 'max:80'],
            'shell_builder_config.primary.*.label' => ['required_with:shell_builder_config.primary', 'string', 'max:80'],
            'shell_builder_config.primary.*.icon' => ['sometimes', 'nullable', 'string', 'max:80'],
            'shell_builder_config.primary.*.offline' => ['sometimes', 'boolean'],
            'shell_builder_config.drawer'   => ['sometimes', 'array', 'max:40'],
            'shell_builder_config.drawer.*.id' => ['required_with:shell_builder_config.drawer', 'string', 'max:80'],
            'shell_builder_config.drawer.*.label' => ['required_with:shell_builder_config.drawer', 'string', 'max:80'],
            'shell_builder_config.settings_sections' => ['sometimes', 'array', 'max:20'],
            'shell_builder_config.settings_sections.*' => ['string', 'max:80'],
            'shell_builder_config.home_widgets' => ['sometimes', 'array', 'max:20'],
            'shell_builder_config.home_widgets.*' => ['string', 'max:80'],
            'shell_builder_config.workspace_templates' => ['sometimes', 'array', 'max:12'],
            'shell_builder_config.workspace_templates.*' => ['string', 'max:80'],
            'shell_builder_config.default_view' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $suggestedPrompts = collect($this->input('suggested_prompts', []))
            ->map(fn ($prompt) => [
                'name'   => Arr::get($prompt, 'name'),
                'prompt' => Arr::get($prompt, 'prompt'),
            ])
            ->filter(fn ($prompt) => filled($prompt['name']) || filled($prompt['prompt']))
            ->values()
            ->all();

        $domains = is_array($this->trusted_domains)
            ? $this->trusted_domains
            : explode(',', trim($this->trusted_domains ?? ''));

        $trusted_domains = array_values(
            array_filter(
                array_map(
                    fn ($domain) => rtrim(
                        preg_replace('#^(https?://)?#i', '', trim($domain)),
                        '/'
                    ),
                    $domains
                )
            )
        );

        $this->merge([
            'avatar'                    => $this->input('avatar') ?: ChatbotAvatar::query()->first()?->getAttribute('avatar'),
            'uuid'                      => Str::uuid()->toString(),
            'user_id'                   => Auth::id(),
            'ai_model'                  => Setting::getCache()->openai_default_model,
            'ai_embedding_model'        => $this->get('ai_embedding_model') ?: EntityEnum::TEXT_EMBEDDING_3_SMALL->value,
            'suggested_prompts'         => $suggestedPrompts,
            'suggested_prompts_enabled' => (bool) $this->boolean('suggested_prompts_enabled'),
            'trusted_domains'           => $trusted_domains,
        ]);
    }
}
