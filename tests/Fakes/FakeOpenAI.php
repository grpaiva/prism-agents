<?php

namespace Grpaiva\PrismAgents\Tests\Fakes;

use Prism\Prism\Providers\OpenAI\OpenAI;

readonly class FakeOpenAI extends OpenAI
{
    public function run(...$args): array
    {
        return [
            'output' => 'This is a mocked response from the AI.',
            'model' => 'gpt-4o',
            'usage' => [
                'input_tokens' => 50,
                'output_tokens' => 20,
                'total_tokens' => 70,
            ],
        ];
    }
}
