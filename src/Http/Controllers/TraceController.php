<?php

namespace Grpaiva\PrismAgents\Http\Controllers;

use Grpaiva\PrismAgents\Models\PrismAgentExecution;
use Grpaiva\PrismAgents\Models\PrismAgentStep;
use Grpaiva\PrismAgents\Models\PrismAgentToolCall;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TraceController extends Controller
{
    /**
     * Display a listing of traces
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get parent executions (workflows)
        $parentExecutions = PrismAgentExecution::with(['children', 'steps.toolCalls.result'])
            ->whereNull('parent_id')
            ->orderBy('started_at', 'desc')
            ->paginate(20);
        
        // Group executions by workflow
        $workflowGroups = [];
        foreach ($parentExecutions as $parent) {
            $workflow = [
                'parent' => $parent,
                'children' => $parent->children()->orderBy('started_at', 'asc')->get()
            ];
            $workflowGroups[] = $workflow;
        }
        
        return view('prism-agents::traces.index', [
            'workflowGroups' => $workflowGroups,
            'executions' => $parentExecutions, // Keep for pagination
        ]);
    }
    
    /**
     * Display a specific trace
     * 
     * @param Request $request
     * @param string $executionId
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $executionId)
    {
        // Get the root execution by ID
        $rootExecution = PrismAgentExecution::with([
            'steps' => function($query) {
                $query->orderBy('step_index');
            },
            'steps.toolCalls',
            'steps.toolCalls.result',
            'steps.messages' => function($query) {
                $query->orderBy('message_index');
            },
            'children',
            'children.steps' => function($query) {
                $query->orderBy('step_index');
            }
        ])->find($executionId);
        
        if (!$rootExecution) {
            abort(404, 'Trace not found');
        }
        
        // Calculate total duration
        $totalDuration = $rootExecution->duration ?: 1; // Default to 1ms to avoid division by zero
        
        // Build the hierarchical trace structure
        $hierarchicalTraces = $this->buildHierarchy($rootExecution);
        
        return view('prism-agents::traces.show', [
            'executionId' => $executionId,
            'rootExecution' => $rootExecution,
            'hierarchicalTraces' => $hierarchicalTraces,
            'totalDuration' => $totalDuration,
        ]);
    }

    /**
     * Build a hierarchical view of the execution trace
     * 
     * @param PrismAgentExecution $execution
     * @return array
     */
    private function buildHierarchy(PrismAgentExecution $execution): array
    {
        $result = [];
        $level = 0;

        // Add the root execution
        $result[] = [
            'model' => [
                'id' => $execution->id,
                'type' => 'agent_execution',
                'name' => $execution->name,
                'started_at' => $execution->started_at,
                'duration' => $execution->duration,
                'parent_id' => null,
                'metadata' => [
                    'provider' => $execution->provider,
                    'model' => $execution->model,
                    'status' => $execution->status
                ]
            ],
            'level' => $level,
            'visible' => true,
            'children' => []
        ];

        // Add steps for the execution
        foreach ($execution->steps as $step) {
            $stepLevel = $level + 1;
            $stepItem = [
                'model' => [
                    'id' => $step->id,
                    'type' => 'llm_step',
                    'name' => 'Step ' . $step->step_index,
                    'started_at' => $step->started_at,
                    'duration' => $step->duration,
                    'parent_id' => $execution->id,
                    'metadata' => [
                        'step_index' => $step->step_index,
                        'input' => $step->messages->where('message_index', 0)->first()?->content,
                        'output' => $step->text,
                        'finish_reason' => $step->finish_reason,
                        'tools' => $step->toolCalls->pluck('name')->toArray()
                    ]
                ],
                'level' => $stepLevel,
                'visible' => true,
                'children' => []
            ];
            
            $result[] = $stepItem;

            // Add tool calls for the step
            foreach ($step->toolCalls as $toolCall) {
                $toolLevel = $stepLevel + 1;
                $toolItem = [
                    'model' => [
                        'id' => $toolCall->id,
                        'type' => 'handoff',
                        'name' => $toolCall->name,
                        'started_at' => $toolCall->started_at,
                        'duration' => $toolCall->duration,
                        'parent_id' => $step->id,
                        'metadata' => [
                            'tool_name' => $toolCall->name,
                            'args' => $toolCall->args,
                            'result' => $toolCall->result?->result
                        ]
                    ],
                    'level' => $toolLevel,
                    'visible' => false,
                    'children' => []
                ];
                
                $result[] = $toolItem;
            }
        }

        // Add child executions recursively
        foreach ($execution->children as $childExecution) {
            $childLevel = $level + 1;
            $childItem = [
                'model' => [
                    'id' => $childExecution->id,
                    'type' => 'agent_run',
                    'name' => $childExecution->name,
                    'started_at' => $childExecution->started_at,
                    'duration' => $childExecution->duration,
                    'parent_id' => $execution->id,
                    'metadata' => [
                        'provider' => $childExecution->provider,
                        'model' => $childExecution->model,
                        'status' => $childExecution->status
                    ]
                ],
                'level' => $childLevel,
                'visible' => false,
                'children' => []
            ];
            
            $result[] = $childItem;

            // Add steps for child executions
            foreach ($childExecution->steps as $step) {
                $stepLevel = $childLevel + 1;
                $stepItem = [
                    'model' => [
                        'id' => $step->id,
                        'type' => 'llm_step',
                        'name' => 'Step ' . $step->step_index,
                        'started_at' => $step->started_at,
                        'duration' => $step->duration,
                        'parent_id' => $childExecution->id,
                        'metadata' => [
                            'step_index' => $step->step_index,
                            'input' => $step->messages->where('message_index', 0)->first()?->content,
                            'output' => $step->text,
                            'finish_reason' => $step->finish_reason,
                            'tools' => $step->toolCalls->pluck('name')->toArray()
                        ]
                    ],
                    'level' => $stepLevel,
                    'visible' => false,
                    'children' => []
                ];
                
                $result[] = $stepItem;

                // Add tool calls for child steps
                foreach ($step->toolCalls as $toolCall) {
                    $toolLevel = $stepLevel + 1;
                    $toolItem = [
                        'model' => [
                            'id' => $toolCall->id,
                            'type' => 'handoff',
                            'name' => $toolCall->name,
                            'started_at' => $toolCall->started_at,
                            'duration' => $toolCall->duration,
                            'parent_id' => $step->id,
                            'metadata' => [
                                'tool_name' => $toolCall->name,
                                'args' => $toolCall->args,
                                'result' => $toolCall->result?->result
                            ]
                        ],
                        'level' => $toolLevel,
                        'visible' => false,
                        'children' => []
                    ];
                    
                    $result[] = $toolItem;
                }
            }
        }

        // Build parent-child relationships
        foreach ($result as &$item) {
            foreach ($result as $potentialChild) {
                if ($potentialChild['model']['parent_id'] === $item['model']['id']) {
                    $item['children'][] = $potentialChild['model']['id'];
                }
            }
        }

        return $result;
    }
} 