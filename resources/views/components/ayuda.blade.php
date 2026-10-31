{{-- Explicación sencilla y desplegable de una sección. Uso: <x-ayuda id="ayuda-x" titulo="...">…</x-ayuda> --}}
@props(['id', 'titulo' => '¿Qué significa esto?'])

<div class="collapse" id="{{ $id }}">
    <div class="ayuda">
        <div class="ayuda-head">
            <span class="ayuda-ico"><i class="bi bi-lightbulb-fill"></i></span>
            <strong>{{ $titulo }}</strong>
            <button type="button" class="btn-close ms-auto" data-bs-toggle="collapse" data-bs-target="#{{ $id }}" aria-label="Cerrar explicación"></button>
        </div>
        <div class="ayuda-body">{{ $slot }}</div>
    </div>
</div>
