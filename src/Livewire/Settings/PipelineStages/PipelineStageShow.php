<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\PipelineStages;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\PipelineStage;

class PipelineStageShow extends Component
{
    use AuthorizesRequests;
    use Toast;

    public PipelineStage $pipelineStage;

    public function delete($id)
    {
        if ($pipelineStage = PipelineStage::find($id)) {
            $this->authorize('delete', $pipelineStage);

            $pipelineStage->delete();

            $this->success(ucfirst(trans('laravel-crm::lang.pipeline_stage_deleted')), redirectTo: route('laravel-crm.pipeline-stages.index'));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.pipeline-stages.pipeline-stage-show');
    }
}
