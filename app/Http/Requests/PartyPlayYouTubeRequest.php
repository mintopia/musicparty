<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PartyPlayYouTubeRequest extends FormRequest
{
    private const array HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'];

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'video' => ['required', 'string', 'url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.url' => 'Enter a valid YouTube video URL.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('video')) {
                    return;
                }
                if ($this->videoId() === null) {
                    $validator->errors()->add('video', 'Enter a valid YouTube video URL.');
                }
            },
        ];
    }

    public function videoId(): ?string
    {
        $url = parse_url((string) $this->input('video'));
        $host = isset($url['host']) ? strtolower($url['host']) : null;
        if ($host === null || ! in_array($host, self::HOSTS, true)) {
            return null;
        }

        if ($host === 'youtu.be') {
            $videoId = ltrim($url['path'] ?? '', '/');
        } else {
            $query = [];
            parse_str($url['query'] ?? '', $query);
            $videoId = $query['v'] ?? null;
        }

        return is_string($videoId) && $videoId !== '' ? $videoId : null;
    }
}
