<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('tms-tickets::tickets.heading') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f3f4f6; color: #111827; }
        main { max-width: 36rem; margin: 2rem auto; background: #fff; border-radius: .5rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        h1 { font-size: 1.25rem; margin: 0 0 1rem; }
        label { display: block; font-weight: 600; font-size: .875rem; margin-top: 1rem; }
        input, textarea, select { box-sizing: border-box; width: 100%; margin-top: .25rem; padding: .5rem; border: 1px solid #d1d5db; border-radius: .375rem; font: inherit; }
        .error { color: #b91c1c; font-size: .875rem; margin-top: .25rem; }
        .notice { background: #ecfdf5; color: #065f46; padding: .75rem; border-radius: .375rem; margin-bottom: 1rem; }
        button { margin-top: 1.25rem; background: #1d4ed8; color: #fff; border: 0; border-radius: .375rem; padding: .6rem 1.1rem; font: inherit; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('tms-tickets::tickets.heading') }}</h1>

    @if ($ticketNumber)
        <p class="notice">{{ __('tms-tickets::tickets.success.title') }}: {{ __('tms-tickets::tickets.success.body', ['number' => $ticketNumber]) }}</p>
    @endif

    @if ($errors->has('ticket'))
        <p class="error" role="alert">{{ $errors->first('ticket') }}</p>
    @endif

    <form method="POST" action="{{ route('tms-tickets.report.store') }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <label for="title">{{ __('tms-tickets::tickets.fields.title') }}</label>
        <input id="title" name="title" type="text" maxlength="255" required value="{{ old('title') }}">
        @error('title') <div class="error">{{ $message }}</div> @enderror

        <label for="description">{{ __('tms-tickets::tickets.fields.description') }}</label>
        <textarea id="description" name="description" rows="5" maxlength="10000" required>{{ old('description') }}</textarea>
        @error('description') <div class="error">{{ $message }}</div> @enderror

        <label for="priority">{{ __('tms-tickets::tickets.fields.priority') }}</label>
        <select id="priority" name="priority" required>
            @foreach (['low', 'medium', 'high', 'critical'] as $priority)
                <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ __('tms-tickets::tickets.priorities.'.$priority) }}</option>
            @endforeach
        </select>

        <input type="hidden" name="page_url" value="{{ old('page_url', $pageUrl) }}">

        <button type="submit">{{ __('tms-tickets::tickets.submit') }}</button>
    </form>
</main>
</body>
</html>
