@props(['titulo' => 'Revise los datos ingresados'])

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="alerta-contenido">
            <strong>{{ $titulo }}</strong>
            <ul class="alerta-lista">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
