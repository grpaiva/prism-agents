<?php

use Grpaiva\PrismAgents\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a mock for Prism's response
 */
function mockPrismResponse($responseData) {
    // Create a mock response object
    if (is_string($responseData)) {
        $response = Mockery::mock(Prism\Prism\Response::class);
        $response->text = $responseData;
        $response->toolResults = [];
        $response->steps = [];
    } else {
        $response = Mockery::mock(Prism\Prism\Response::class);
        $response->text = $responseData->text;
        $response->toolResults = $responseData->toolResults ?? [];
        $response->steps = $responseData->steps ?? [];
    }
    
    // Create a fluent mock that handles the method chain
    $fluent = Mockery::mock();
    $fluent->shouldReceive('using')->andReturnSelf();
    $fluent->shouldReceive('withSystemPrompt')->andReturnSelf();
    $fluent->shouldReceive('withPrompt')->andReturnSelf();
    $fluent->shouldReceive('withMessage')->andReturnSelf();
    $fluent->shouldReceive('withTools')->andReturnSelf();
    $fluent->shouldReceive('asText')->andReturn($response);
    
    // Mock the Prism facade
    $prismMock = Mockery::mock('alias:Prism\Prism\Prism');
    $prismMock->shouldReceive('text')->andReturn($fluent);
    
    return $response;
} 