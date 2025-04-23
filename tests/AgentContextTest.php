<?php

namespace Grpaiva\PrismAgents\Tests;

use Grpaiva\PrismAgents\AgentContext;
use PHPUnit\Framework\Attributes\Test;

class AgentContextTest extends TestCase
{
    #[Test]
    public function it_can_create_a_context()
    {
        $context = new AgentContext(['key' => 'value']);
        
        $this->assertEquals(['key' => 'value'], $context->all());
    }
    
    /** @test */
    public function it_can_get_a_value_from_context()
    {
        $context = new AgentContext(['key' => 'value']);
        
        $this->assertEquals('value', $context->get('key'));
    }
    
    /** @test */
    public function it_returns_default_when_key_not_found()
    {
        $context = new AgentContext(['key' => 'value']);
        
        $this->assertEquals('default', $context->get('missing', 'default'));
    }
    
    /** @test */
    public function it_can_set_a_value_in_context()
    {
        $context = new AgentContext();
        
        $context->set('key', 'value');
        
        $this->assertEquals('value', $context->get('key'));
    }
    
    /** @test */
    public function it_can_check_if_key_exists()
    {
        $context = new AgentContext(['key' => 'value']);
        
        $this->assertTrue($context->has('key'));
        $this->assertFalse($context->has('missing'));
    }
    
    /** @test */
    public function it_can_remove_a_key()
    {
        $context = new AgentContext(['key' => 'value']);
        
        $context->remove('key');
        
        $this->assertFalse($context->has('key'));
    }
    
    /** @test */
    public function it_can_create_child_context()
    {
        $parent = new AgentContext(['parent_key' => 'parent_value']);
        $child = $parent->createChild(['child_key' => 'child_value']);
        
        $this->assertEquals('parent_value', $child->get('parent_key'));
        $this->assertEquals('child_value', $child->get('child_key'));
        $this->assertSame($parent, $child->getParent());
    }
    
    /** @test */
    public function it_prioritizes_child_values_over_parent()
    {
        $parent = new AgentContext(['key' => 'parent_value']);
        $child = $parent->createChild(['key' => 'child_value']);
        
        $this->assertEquals('child_value', $child->get('key'));
    }
    
    /** @test */
    public function it_can_merge_parent_and_child_contexts()
    {
        $parent = new AgentContext(['parent_key' => 'parent_value', 'shared_key' => 'parent_value']);
        $child = $parent->createChild(['child_key' => 'child_value', 'shared_key' => 'child_value']);
        
        $merged = $child->merged();
        
        $this->assertEquals([
            'parent_key' => 'parent_value',
            'shared_key' => 'child_value',
            'child_key' => 'child_value'
        ], $merged);
    }
} 