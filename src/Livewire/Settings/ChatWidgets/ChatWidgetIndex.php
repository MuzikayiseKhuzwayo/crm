<?php

namespace VentureDrake\LaravelCrm\Livewire\Settings\ChatWidgets;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\ChatWidget;

class ChatWidgetIndex extends Component
{
    use AuthorizesRequests;
    use Toast, WithPagination;

    public $layout = 'index';

    public function widgets(): LengthAwarePaginator
    {
        return ChatWidget::query()->latest()->paginate(25);
    }

    public function delete(int $id): void
    {
        if ($w = ChatWidget::find($id)) {
            $this->authorize('delete', $w);

            $w->delete();
            $this->success(ucfirst(trans('laravel-crm::lang.chat_widget_deleted')));
        }
    }

    public function render()
    {
        return view('laravel-crm::livewire.settings.chat-widgets.chat-widget-index', [
            'widgets' => $this->widgets(),
        ]);
    }
}
