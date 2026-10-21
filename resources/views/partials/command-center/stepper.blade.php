{{-- Incident workflow stepper. Expects $stage (ResponseStage); optional $interactive (manager stage picker) and $compact. --}}
@php
    $current = $stage->position();
    $interactive = $interactive ?? false;
@endphp

<ol @class(['cc-stepper', 'is-compact' => $compact ?? false]) style="--cc-ratio: {{ $stage->percent() / 100 }}" aria-label="Workflow: {{ $stage->label() }}">
    @foreach (\App\Enums\ResponseStage::cases() as $step)
        <li @class(['cc-step', 'cc-tone-'.$step->tone(), 'is-done' => $step->position() < $current, 'is-current' => $step === $stage])
            @if ($step === $stage) aria-current="step" @endif>
            @if ($interactive)
                <button type="button" class="cc-step-dot" data-stage-pick="{{ $step->value }}" data-stage-label="{{ $step->label() }}"
                        @disabled($step === $stage) title="{{ $step === $stage ? 'Current stage' : 'Move the case to '.$step->label() }}">
                    <x-icon :name="$step->position() < $current ? 'check' : $step->icon()" />
                </button>
            @else
                <span class="cc-step-dot"><x-icon :name="$step->position() < $current ? 'check' : $step->icon()" /></span>
            @endif
            <span class="cc-step-label">{{ $step->label() }}</span>
        </li>
    @endforeach
</ol>
