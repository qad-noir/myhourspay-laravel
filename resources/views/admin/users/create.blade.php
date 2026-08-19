@extends('layouts.admin') @section('title','Create user') @section('content')
<a class="admin-context-back" href="{{ route('admin.users.index') }}"><svg viewBox="0 0 20 20"><path d="m12.5 5-5 5 5 5"/></svg>Back to users</a>
<section class="admin-card admin-form-card admin-form-card--standalone"><header><div><h2>New user account</h2><p>A secure password-setup link will be emailed after creation.</p></div></header><form method="POST" action="{{ route('admin.users.store') }}">@csrf<label>Name<input name="name" value="{{ old('name') }}" required></label><label>Email<input type="email" name="email" value="{{ old('email') }}" required></label><button>Create and send setup email</button></form></section>
@endsection
