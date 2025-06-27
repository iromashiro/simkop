@props([
'id' => null,
'label' => null,
'type' => 'text',
'required' => false,
'error' => null,
'help' => null,
'value' => null,
'placeholder' => null,
'autocomplete' => null,
'ariaDescribedBy' => null
])

@php
$fieldId = $id ?? 'field_' . Str::random(8);
$errorId = $fieldId . '_error';
$helpId = $fieldId . '_help';
$describedBy = collect([$ariaDescribedBy, $error ? $errorId : null, $help ? $helpId : null])
->filter()
->implode(' ');
@endphp

<div class="mb-3">
    @if($label)
    <label for="{{ $fieldId }}" class="form-label">
        {{ $label }}
        @if($required)
        <span class="text-danger" aria-label="required">*</span>
        @endif
    </label>
    @endif

    <input type="{{ $type }}" id="{{ $fieldId }}" name="{{ $attributes->get('name', $fieldId) }}"
        class="form-control @error($attributes->get('name', $fieldId)) is-invalid @enderror"
        value="{{ old($attributes->get('name', $fieldId), $value) }}" @if($placeholder) placeholder="{{ $placeholder }}"
        @endif @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($required) required aria-required="true"
        @endif @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['name', 'class']) }}>

    @if($help)
    <div id="{{ $helpId }}" class="form-text">
        {{ $help }}
    </div>
    @endif

    @if($error)
    <div id="{{ $errorId }}" class="invalid-feedback" role="alert">
        {{ $error }}
    </div>
    @endif

    @error($attributes->get('name', $fieldId))
    <div id="{{ $errorId }}" class="invalid-feedback" role="alert">
        {{ $message }}
    </div>
    @enderror
</div>
