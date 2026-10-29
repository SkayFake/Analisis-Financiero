@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Revise los datos ingresados:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
