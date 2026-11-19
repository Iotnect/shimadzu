<x-app-layout title="Settings">
    <div class="settings-page">
    <header class="settings-heading">
        <h1>Settings</h1>
        <p>Manage station parameters and workspace appearance.</p>
    </header>

    @php
        $minutes = fn ($value) => $value === null ? 'Not configured' : rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
    @endphp
    <section class="panel station-settings-panel" aria-labelledby="station-settings-heading">
        <div class="settings-section-heading">
            <span class="settings-section-icon"><x-icon name="settings" /></span>
            <div>
                <h2 id="station-settings-heading">Station Settings</h2>
                <p>Set cycle targets and working time for each line and station.</p>
            </div>
        </div>
        <div class="station-settings-body">
            @if(session('station_status'))
                <p class="station-settings-status" role="status">{{ session('station_status') }}</p>
            @endif
            @if($errors->any())
                <div class="station-settings-error" role="alert">
                    <p>Please check the station parameters.</p>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if(!$settingsAvailable)
                <p class="station-settings-note">Demo preview. Changes here are not saved or applied to the dashboard.</p>
            @endif
                <form method="GET" action="{{ route('settings') }}" class="station-sensor-form">
                    <div class="station-setting-field">
                        <label for="settings-sensor">Line</label>
                        <select name="sensor" id="settings-sensor">
                            @foreach($sensors as $name)
                                <option value="{{ $name }}" @selected($sensor === $name)>{{ config('production.sensor_labels.'.$name, $name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="button button-quiet" type="submit">View stations</button>
                    <a class="text-button" href="{{ route('sensors.show', $sensor) }}">Open line dashboard <x-icon name="arrow" /></a>
                </form>

                @if($stations === [])
                    <p class="station-settings-note">Worklog data is unavailable for this line. Import its worklog to configure stations.</p>
                @else
                    <div class="station-list-heading">
                        <h3 id="station-list-heading">Stations</h3>
                        <p><x-icon name="calendar" /> Effective on {{ \Illuminate\Support\Carbon::parse($today)->format('j M Y') }}</p>
                    </div>
                    <div class="table-scroll station-table-frame" role="region" aria-labelledby="station-list-heading" tabindex="0">
                        <table class="station-table station-settings-table station-list-table">
                            <thead><tr><th>Station</th><th>Standard cycle time<br><small>min / cycle</small></th><th>Daily working time<br><small>min / day</small></th><th>Effective from</th><th>Actions</th></tr></thead>
                            <tbody>
                                @foreach($stations as $row)
                                    <tr @if($station === $row['station']) class="station-setting-selected" @endif>
                                        <th scope="row"><span class="station-settings-id">{{ $row['station'] }}</span></th>
                                        <td>{{ $minutes($row['standard']) }}</td>
                                        <td>{{ $minutes($row['working']) }}</td>
                                        <td>{{ $row['effective_from'] === null ? 'Default' : \Illuminate\Support\Carbon::parse($row['effective_from'])->format('j M Y') }}</td>
                                        <td><div class="station-setting-actions">
                                            <a class="text-button" href="{{ route('settings', ['sensor' => $sensor, 'station' => $row['station']]) }}#station-editor" aria-label="Edit parameters for {{ config('production.sensor_labels.'.$sensor, $sensor) }} {{ $row['station'] }}">Edit parameters</a>
                                            <a class="text-button" href="{{ route('settings', ['sensor' => $sensor, 'station' => $row['station']]) }}#station-history" aria-label="View history for {{ config('production.sensor_labels.'.$sensor, $sensor) }} {{ $row['station'] }}">View history</a>
                                        </div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <section id="station-editor" class="station-settings-section" aria-labelledby="station-editor-heading">
                        <div class="station-editor-heading">
                            <div>
                                <h3 id="station-editor-heading">Edit station parameters</h3>
                                <p>{{ $settingsAvailable ? 'Dashboard metrics use the values effective on each work date.' : 'Try editing the station parameters in this demo preview.' }}</p>
                            </div>
                            <span class="station-editor-scope">{{ config('production.sensor_labels.'.$sensor, $sensor) }} / {{ $station }}</span>
                        </div>
                        <form method="POST" action="{{ route('settings.stations.store') }}" id="station-parameters-form" data-preview="{{ $settingsAvailable ? 'false' : 'true' }}">
                            @csrf
                            <input type="hidden" name="sensor" value="{{ $sensor }}">
                            <input type="hidden" name="station" value="{{ $station }}">
                            <div class="station-parameter-grid">
                                <div class="station-setting-field"><label for="standard-minutes">Standard cycle time (min / cycle)</label>
                                    <input id="standard-minutes" name="standard_minutes" type="number" step="0.0001" min="0.0001" max="99999999" value="{{ old('standard_minutes', $stationParameters['standard']) }}" aria-describedby="standard-help" @if($errors->has('standard_minutes')) aria-invalid="true" @endif>
                                    <small id="standard-help">Leave empty when a cycle standard is unavailable.</small>
                                </div>
                                <div class="station-setting-field"><label for="working-minutes">Daily working time (min / day)</label>
                                    <input id="working-minutes" name="working_minutes" type="number" step="0.0001" min="0.0001" max="1440" required value="{{ old('working_minutes', $stationParameters['working']) }}" @if($errors->has('working_minutes')) aria-invalid="true" @endif>
                                </div>
                                <div class="station-setting-field"><label for="effective-from">Effective from</label>
                                    <input id="effective-from" name="effective_from" type="date" required value="{{ old('effective_from', $today) }}" @if($errors->has('effective_from')) aria-invalid="true" @endif>
                                </div>
                                <div class="station-setting-field station-setting-notes"><label for="station-notes">Meeting notes / reason for change</label>
                                    <textarea id="station-notes" name="notes" rows="3" maxlength="1000" required @if($errors->has('notes')) aria-invalid="true" @endif>{{ old('notes') }}</textarea>
                                </div>
                            </div>
                            <div class="station-setting-save">
                                <p class="station-settings-note">{{ $settingsAvailable ? 'Past effective dates recalculate historical metrics for this station. If revisions share a date, the latest saved revision applies.' : 'Preview changes to try the form. The dashboard will continue using its current parameters.' }}</p>
                                <button type="submit" class="button button-primary">{{ $settingsAvailable ? 'Save changes' : 'Preview changes' }}</button>
                            </div>
                            @if(!$settingsAvailable)
                                <p id="station-preview-status" class="station-settings-status" role="status" hidden></p>
                                <noscript>Enable JavaScript to preview station parameters.</noscript>
                            @endif
                        </form>
                    </section>

                    <section id="station-history" class="station-settings-section" aria-labelledby="station-history-heading">
                        <div class="station-history-heading">
                            <h3 id="station-history-heading">Change history</h3>
                            <span>{{ config('production.sensor_labels.'.$sensor, $sensor) }} / {{ $station }}</span>
                        </div>
                        @if($history === null)
                            <p class="station-history-empty"><x-icon name="clock" /> Revision history is not saved in this demo preview.</p>
                        @elseif($history->isEmpty())
                            <p class="station-history-empty"><x-icon name="clock" /> No revisions saved. The default parameters are in use.</p>
                        @else
                            <div class="table-scroll station-table-frame" role="region" aria-labelledby="station-history-heading" tabindex="0">
                                <table class="station-table station-settings-table">
                                    <thead><tr><th>Effective from</th><th>Standard<br><small>min / cycle</small></th><th>Working time<br><small>min / day</small></th><th>Notes</th><th>Saved by</th></tr></thead>
                                    <tbody>
                                        @foreach($history as $revision)
                                            <tr><td>{{ $revision->effective_from->format('j M Y') }}@if($revision->effective_from->toDateString() > $today)<small class="station-scheduled">Scheduled</small>@endif</td>
                                                <td>{{ $minutes($revision->standard_minutes) }}</td><td>{{ $minutes($revision->working_minutes) }}</td>
                                                <td class="station-history-note">{{ $revision->notes }}</td>
                                                <td>{{ $revision->changed_by_name }}<small class="station-scheduled">{{ $revision->created_at->timezone('Asia/Kuala_Lumpur')->format('j M Y, H:i') }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($history->hasPages())
                                <nav aria-label="Station history pagination" class="station-setting-actions station-history-pagination">
                                    @if($history->previousPageUrl())<a class="text-button" href="{{ $history->previousPageUrl() }}#station-history">Previous</a>@endif
                                    <span>Page {{ $history->currentPage() }} of {{ $history->lastPage() }}</span>
                                    @if($history->nextPageUrl())<a class="text-button" href="{{ $history->nextPageUrl() }}#station-history">Next</a>@endif
                                </nav>
                            @endif
                        @endif
                    </section>
                @endif
        </div>
    </section>

    <section class="panel settings-panel" aria-labelledby="appearance-heading">
        <div class="settings-section-heading">
            <h2 id="appearance-heading">Appearance</h2>
        </div>
        <div id="appearance-settings">
            <noscript>Enable JavaScript to change the theme.</noscript>
        </div>
    </section>
    </div>
</x-app-layout>
