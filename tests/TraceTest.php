<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\Trace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TraceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the traces table
        $this->artisan('migrate', [
            '--database' => 'testbench',
        ])->run();
    }
    
    /** @test */
    public function it_can_create_a_trace()
    {
        $trace = new Trace();
        
        $this->assertNotNull($trace->getTraceId());
    }
    
    /** @test */
    public function it_can_create_a_trace_with_specific_id()
    {
        $traceId = 'test-trace-id';
        $trace = new Trace($traceId);
        
        $this->assertEquals($traceId, $trace->getTraceId());
    }
    
    /** @test */
    public function it_can_start_and_end_a_span()
    {
        $trace = new Trace();
        
        $spanId = $trace->startSpan('test-span', 'test');
        
        $this->assertTrue($trace->isSpanActive($spanId));
        $this->assertEquals($spanId, $trace->getCurrentSpanId());
        
        $trace->endSpan($spanId);
        
        $this->assertFalse($trace->isSpanActive($spanId));
        $this->assertNull($trace->getCurrentSpanId());
    }
    
    /** @test */
    public function it_can_end_a_span_with_metadata()
    {
        $trace = new Trace();
        
        $spanId = $trace->startSpan('test-span', 'test', ['start' => 'metadata']);
        $trace->endSpan($spanId, ['end' => 'metadata']);
        
        $spans = $trace->getSpans();
        $this->assertEquals([
            'start' => 'metadata',
            'end' => 'metadata'
        ], $spans[$spanId]['metadata']);
    }
    
    /** @test */
    public function it_nests_spans_correctly()
    {
        $trace = new Trace();
        
        $parentSpanId = $trace->startSpan('parent', 'test');
        $childSpanId = $trace->startSpan('child', 'test');
        
        $spans = $trace->getSpans();
        
        $this->assertEquals($parentSpanId, $spans[$childSpanId]['parent_id']);
        
        // End spans in reverse order
        $trace->endSpan($childSpanId);
        $trace->endSpan($parentSpanId);
        
        $this->assertFalse($trace->isSpanActive($childSpanId));
        $this->assertFalse($trace->isSpanActive($parentSpanId));
    }
    
    /** @test */
    public function it_saves_spans_to_database()
    {
        Config::set('prism-agents.tracing.enabled', true);
        
        $trace = new Trace('db-test-trace');
        
        $spanId = $trace->startSpan('test-span', 'test', ['key' => 'value']);
        $trace->endSpan($spanId, ['result' => 'success']);
        
        // Check if data was saved to DB
        $dbSpan = DB::table('prism_agent_traces')
            ->where('id', $spanId)
            ->first();
        
        $this->assertNotNull($dbSpan);
        $this->assertEquals('db-test-trace', $dbSpan->trace_id);
        $this->assertEquals('test-span', $dbSpan->name);
        $this->assertEquals('test', $dbSpan->type);
        
        // Verify metadata was JSON encoded
        $metadata = json_decode($dbSpan->metadata, true);
        $this->assertEquals('value', $metadata['key']);
        $this->assertEquals('success', $metadata['result']);
    }
    
    /** @test */
    public function it_doesnt_save_to_database_when_disabled()
    {
        Config::set('prism-agents.tracing.enabled', false);
        
        $trace = new Trace('disabled-test-trace');
        
        $spanId = $trace->startSpan('test-span', 'test');
        $trace->endSpan($spanId);
        
        // Check that nothing was saved to DB
        $dbSpan = DB::table('prism_agent_traces')
            ->where('id', $spanId)
            ->first();
        
        $this->assertNull($dbSpan);
    }
} 