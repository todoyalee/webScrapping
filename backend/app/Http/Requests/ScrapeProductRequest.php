<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScrapeProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'url:http,https', 'max:512'],
        ];
    }

    /**
     * The page to scrape, falling back to the configured default target.
     */
    public function targetUrl(): string
    {
        return $this->input('url', config('scraper.target_url'));
    }
}
