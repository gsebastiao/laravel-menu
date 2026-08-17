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
    $items ??= Menu::forUser();
@endphp

@if (count($items) > 0)
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
            @endphp

            <li @class([
                'menu-item',
                'is-active' => Menu::isActive($item),
                'has-children' => count($children) > 0,
            ])>
                <a class="menu-link"
                   @if ($url) href="{{ $url }}" @endif
                   @if ($target) target="{{ $target }}" @endif
                   @if ($target === '_blank') rel="noopener noreferrer" @endif
                   @if (filled($item['description'] ?? null)) title="{{ $item['description'] }}" @endif>
                    @if (filled($item['icon'] ?? null))
                        <i class="menu-icon {{ $item['icon'] }}" aria-hidden="true"></i>
                    @endif
                    <span class="menu-label">{{ $item['label'] }}</span>
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
