<x-app-layout title="Production dashboard">
    <div class="page-heading"><h1>Production dashboard</h1></div>

    <section class="panel" aria-labelledby="worklog-unavailable">
        <div class="panel-heading">
            <div>
                <h2 id="worklog-unavailable">Production worklog unavailable</h2>
                <p>Contact your workspace administrator if the worklog remains unavailable.</p>
            </div>
            <a class="button button-quiet" href="{{ request()->fullUrl() }}">Retry</a>
        </div>
    </section>
</x-app-layout>
