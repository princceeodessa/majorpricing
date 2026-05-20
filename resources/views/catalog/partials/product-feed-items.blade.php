@foreach ($products as $index => $product)
    @include('catalog.partials.product-card', [
        'product' => $product,
        'delay' => (($index + ($animationOffset ?? 0)) % ($animationWindow ?? 8)) * ($animationStep ?? 60),
    ])
@endforeach

