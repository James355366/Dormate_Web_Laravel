@include('layouts.head', ['title' => 'Unable to open page', 'stylesheet' => null])
<main id="main" class="pending-wrap"><section class="panel p-5"><h1>{{ $status }}</h1><p>{{ $message }}</p>
<a class="btn btn-primary" href="{{ route('dormate.dashboard') }}">Back to overview</a></section></main></body></html>
