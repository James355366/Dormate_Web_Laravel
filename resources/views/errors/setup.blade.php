@include('layouts.head', ['title' => 'Database setup', 'stylesheet' => null])
<main id="main" class="pending-wrap"><section class="panel p-5">
<h1>Connect DorMate to MySQL</h1><p>Start MySQL in XAMPP and check the DB settings in your Laravel .env file.</p>
<p>For a new empty database, run <code>php artisan migrate</code>. For an existing DorMate database, follow the existing-data instructions in START_HERE.md.</p>
<a class="btn btn-primary" href="{{ route('dormate.home') }}">Back to home</a>
</section></main></body></html>
