<?php

namespace App\Extensions\Chatbot\System\Http\Requests;

use App\Extensions\Chatbot\System\Enums\ColorModeEnum;
use App\Extensions\Chatbot\System\Enums\HeaderBgEnum;
use App\Extensions\Chatbot\System\Enums\PositionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ChatbotCustomizeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id'                                 => ['required', 'integer', 'exists:ext_chatbots,id'],
            'interaction_type'                   => ['sometimes', 'nullable', 'string'],
            'uuid'                               => ['required', 'string'],
            'user_id'                            => ['required', 'integer', 'exists:users,id'],
            'title'                              => ['required', 'string'],
            'bubble_message'                     => ['required', 'string'],
            'welcome_message'                    => ['required', 'string'],
            'connect_message'                    => ['sometimes', 'nullable', 'string'],
            'instructions'                       => ['required', 'string'],
            'do_not_go_beyond_instructions'      => ['sometimes', 'nullable'],
            'suggested_prompts'                  => ['sometimes', 'nullable', 'array'],
            'suggested_prompts.*.name'           => ['sometimes', 'nullable', 'string'],
            'suggested_prompts.*.prompt'         => ['sometimes', 'nullable', 'string'],
            'suggested_prompts_enabled'          => ['sometimes', 'boolean'],
            'language'                           => ['sometimes', 'nullable', 'string'],
            'ai_model'                           => ['required', 'string'],
            'logo'                               => ['sometimes', 'nullable', 'string'],
            'avatar'                             => ['sometimes', 'nullable', 'string'],
            'trigger_avatar_size'                => ['sometimes', 'nullable', 'string'],
            'trigger_background'                 => ['sometimes', 'nullable', 'string'],
            'trigger_foreground'                 => ['sometimes', 'nullable', 'string'],
            'color_mode'                         => ['string', Rule::enum(ColorModeEnum::class)],
            'color'                              => ['sometimes', 'nullable', 'string'],
            'show_logo'                          => ['sometimes', 'boolean'],
            'show_date_and_time'                 => ['sometimes', 'boolean'],
            'show_average_response_time'         => ['sometimes', 'boolean'],
            'active'                             => ['sometimes', 'boolean'],
            'position'                           => ['string', Rule::enum(PositionEnum::class)],
            'footer_link'                        => ['sometimes', 'nullable', 'string'],
            'whatsapp_link'                      => ['sometimes', 'nullable', 'string'],
            'telegram_link'                      => ['sometimes', 'nullable', 'string'],
            'facebook_link'                      => ['sometimes', 'nullable', 'string'],
            'instagram_link'                     => ['sometimes', 'nullable', 'string'],
            'watch_product_tour_link'            => ['sometimes', 'nullable', 'string'],
            'show_social_links_in_first_message' => ['sometimes', 'nullable', 'boolean'],
            'is_email_collect'                   => ['sometimes', 'nullable', 'boolean'],
            'is_contact'                         => ['sometimes', 'nullable', 'boolean'],
            'is_attachment'                      => ['sometimes', 'nullable', 'boolean'],
            'is_emoji'                           => ['sometimes', 'nullable', 'boolean'],
            'is_articles'                        => ['sometimes', 'nullable', 'boolean'],
            'is_links'                           => ['sometimes', 'nullable', 'boolean'],
            'is_gdpr'                            => ['sometimes', 'nullable', 'boolean'],
            'privacy_policy_link'                => ['sometimes', 'nullable', 'string'],
            'terms_of_service_link'              => ['sometimes', 'nullable', 'string'],
            'header_bg_type'                     => ['string', Rule::enum(HeaderBgEnum::class)],
            'header_bg_color'                    => ['sometimes', 'nullable', 'string'],
            'header_bg_gradient'                 => ['sometimes', 'nullable', 'string'],
            'header_bg_image_blob'               => ['sometimes', 'nullable'],
            'welcome_bg_image'                   => ['sometimes', 'nullable', 'string'],
            'welcome_bg_image_blob'              => ['sometimes', 'nullable'],
            'human_agent_conditions'             => ['sometimes', 'nullable', 'array'],
            'is_booking_assistant'               => ['sometimes', 'nullable'],
            'booking_assistant_conditions'       => ['sometimes', 'nullable', 'array'],
            'booking_assistant_iframe'           => ['sometimes', 'nullable', 'string'],
            'voice_call_enabled'                 => ['sometimes', 'boolean'],
            'voice_call_first_message'           => ['nullable', 'string'],
            'trusted_domains'                    => ['sometimes', 'nullable', 'array'],
            'bubble_design'                      => ['sometimes', 'nullable'],
            'promo_banner_image'                 => ['sometimes', 'nullable', 'string'],
            'promo_banner_image_blob'            => ['sometimes', 'nullable'],
            'promo_banner_title'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'promo_banner_description'           => ['sometimes', 'nullable', 'string', 'max:1000'],
            'promo_banner_btn_label'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'promo_banner_btn_link'              => ['sometimes', 'nullable', 'string'],
            'is_review_enabled'                  => ['sometimes', 'nullable', 'boolean'],
            'review_prompt'                      => ['sometimes', 'nullable', 'string'],
            'review_responses'                   => ['sometimes', 'nullable', 'array', 'max:5'],
            'review_responses.*'                 => ['nullable', 'string'],
            'is_shop'                            => ['sometimes', 'boolean'],
            'shop_source'                        => ['sometimes', 'nullable', 'string'],
            'shop_features'                      => ['sometimes', 'nullable', 'array'],
            'shopify_domain'                     => ['sometimes', 'nullable', 'string'],
            'shopify_access_token'               => ['sometimes', 'nullable', 'string'],
            'woocommerce_domain'                 => ['sometimes', 'nullable', 'string'],
            'woocommerce_consumer_key'           => ['sometimes', 'nullable', 'string'],
            'woocommerce_consumer_secret'        => ['sometimes', 'nullable', 'string'],
            'titan_template'                     => ['sometimes', 'nullable', 'string', 'max:80'],
            'shell_builder_config'               => ['sometimes', 'nullable', 'array'],
            'shell_builder_config.device'        => ['sometimes', 'string', Rule::in(['mobile', 'tablet', 'desktop'])],
            'shell_builder_config.role'          => ['sometimes', 'string', 'max:80'],
            'shell_builder_config.state'         => ['sometimes', 'string', Rule::in(['online', 'offline', 'syncing', 'conflict', 'empty', 'populated'])],
            'shell_builder_config.theme'         => ['sometimes', 'string', Rule::in(['light', 'dark', 'system'])],
            'shell_builder_config.primary'       => ['sometimes', 'array', 'max:6'],
            'shell_builder_config.primary.*.id' => ['required_with:shell_builder_config.primary', 'string', 'max:80'],
            'shell_builder_config.primary.*.label' => ['required_with:shell_builder_config.primary', 'string', 'max:80'],
            'shell_builder_config.primary.*.icon' => ['sometimes', 'nullable', 'string', 'max:80'],
            'shell_builder_config.primary.*.offline' => ['sometimes', 'boolean'],
            'shell_builder_config.drawer'        => ['sometimes', 'array', 'max:40'],
            'shell_builder_config.drawer.*.id'   => ['required_with:shell_builder_config.drawer', 'string', 'max:80'],
            'shell_builder_config.drawer.*.label' => ['required_with:shell_builder_config.drawer', 'string', 'max:80'],
            'shell_builder_config.settings_sections' => ['sometimes', 'array', 'max:20'],
            'shell_builder_config.settings_sections.*' => ['string', 'max:80'],
            'shell_builder_config.home_widgets'  => ['sometimes', 'array', 'max:20'],
            'shell_builder_config.home_widgets.*' => ['string', 'max:80'],
            'shell_builder_config.workspace_templates' => ['sometimes', 'array', 'max:12'],
            'shell_builder_config.workspace_templates.*' => ['string', 'max:80'],
            'shell_builder_config.default_view'  => ['sometimes', 'nullable', 'string', 'max:80'],
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
            'active'                    => (bool) $this->active,
            'trigger_avatar_size'       => $this->get('trigger_avatar_size') ?? '60px',
            'suggested_prompts'         => $suggestedPrompts,
            'suggested_prompts_enabled' => (bool) $this->boolean('suggested_prompts_enabled'),
            'trusted_domains'           => $trusted_domains,
        ]);
    }
}
