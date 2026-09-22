{{--
    Menu do gsebastiao/laravel-menu (componente Blade).

    Menu do utilizador autenticado:   <x-laravel-menu::menu />
    Com outros itens:                 <x-laravel-menu::menu :items="$itens" />
    Com atributos na <ul>:            <x-laravel-menu::menu class="nav" id="menu-principal" />

    Para mudar o HTML, publica esta view e edita a cópia:
        php artisan vendor:publish --tag=laravel-menu-views
        -> resources/views/vendor/laravel-menu/components/menu.blade.php
--}}
@use('Gsebastiao\LaravelMenu\Facades\Menu')
@props(['items' => null])

@php
    // collect() aceita arrays, Collections e qualquer iterable (um generator
    // passado em :items rebentava com count()).
    $items = collect($items ?? Menu::forUser());
@endphp

@if ($items->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'menu']) }}>
        @foreach ($items as $item)
            @if ($item['is_separator'] ?? false)
                <li class="menu-separator" role="separator"></li>
                @continue
            @endif

            @php
                $url = Menu::url($item);
                $children = $item['children'] ?? [];
                $target = $url ? ($item['target'] ?? null) : null;
                $active = Menu::isActive($item);

                // Ativo por causa de um filho = antepassado, não a página atual:
                // só a página atual leva aria-current.
                $current = $active && ! collect($children)->contains(fn ($child) => Menu::isActive($child));
            @endphp

            <li @class([
                'menu-item',
                'is-active' => $active,
                'has-children' => count($children) > 0,
            ])>
                <a class="menu-link"
                   @if ($url) href="{{ $url }}" @endif
                   @if ($url && $current) aria-current="page" @endif
                   @if ($target) target="{{ $target }}" @endif
                   @if ($target === '_blank') rel="noopener noreferrer" @endif
                   @if (filled($item['description'] ?? null)) title="{{ $item['description'] }}" @endif>
                    @if (filled($item['icon'] ?? null))
                        <i class="menu-icon {{ $item['icon'] }}" aria-hidden="true"></i>
                    @endif
                    <span class="menu-label">{{ $item['label'] ?? '' }}</span>
                    @if (filled($item['badge'] ?? null))
                        <span class="menu-badge">{{ $item['badge'] }}</span>
                    @endif
                </a>

                @if (count($children) > 0)
                    <x-laravel-menu::menu :items="$children" class="menu-submenu" />
                @endif
            </li>
        @endforeach
    </ul>
@endif
