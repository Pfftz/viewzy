@php
    $channel = core()->getCurrentChannel();
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $channel->home_seo['meta_title'] ?? '' }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{  $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    <!-- Tagline -->
    <div class="container px-[60px] max-lg:px-8 max-sm:px-4 mt-12 mb-8 text-center">
        <h1 class="text-4xl font-bold text-[#062468] mb-4">upgrade cara kamu melihat dunia</h1>
        <p class="text-xl text-gray-600 mb-2">Stylish, Nyaman, Anti Ribet</p>
        <p class="text-lg font-semibold text-gray-800">#SeeWithStyle</p>
    </div>

    <!-- Simple catalogue (no hero/carousels) -->
    <v-home-catalogue>
        <div class="container px-[60px] max-lg:px-8 max-sm:px-4">
            <div class="mt-8 grid grid-cols-3 gap-8 max-1060:grid-cols-2 max-md:mt-5 max-md:justify-items-center max-md:gap-x-4 max-md:gap-y-5">
                <x-shop::shimmer.products.cards.grid count="12" />
            </div>
        </div>
    </v-home-catalogue>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-home-catalogue-template"
        >
            <div class="container px-[60px] max-lg:px-8 max-sm:px-4">
                <!-- Product Grid Card Container -->
                <template v-if="isLoading">
                    <div class="mt-8 grid grid-cols-3 gap-8 max-1060:grid-cols-2 max-md:mt-5 max-md:justify-items-center max-md:gap-x-4 max-md:gap-y-5">
                        <x-shop::shimmer.products.cards.grid count="12" />
                    </div>
                </template>

                <template v-else>
                    <template v-if="products.length">
                        <div class="mt-8 grid grid-cols-3 gap-8 max-1060:grid-cols-2 max-md:mt-5 max-md:justify-items-center max-md:gap-x-4 max-md:gap-y-5">
                            <x-shop::products.card
                                ::mode="'grid'"
                                v-for="product in products"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <div class="m-auto grid w-full place-content-center items-center justify-items-center py-32 text-center">
                            <img
                                class="max-sm:h-[100px] max-sm:w-[100px]"
                                src="{{ bagisto_asset('images/thank-you.png') }}"
                                alt="Empty result"
                                loading="lazy"
                                decoding="async"
                            />

                            <p
                                class="text-xl max-sm:text-sm"
                                role="heading"
                            >
                                @lang('shop::app.categories.view.empty')
                            </p>
                        </div>
                    </template>
                </template>

                <!-- Load More Button -->
                <button
                    class="secondary-button mx-auto mt-[60px] block w-max rounded-2xl px-11 py-3 text-center text-base max-md:rounded-lg max-md:text-sm max-sm:mt-7 max-sm:px-7 max-sm:py-2"
                    @click="loadMoreProducts"
                    v-if="links.next"
                >
                    @lang('shop::app.categories.view.load-more')
                </button>
            </div>
        </script>

        <script type="module">
            app.component('v-home-catalogue', {
                template: '#v-home-catalogue-template',

                data() {
                    return {
                        isLoading: true,

                        products: [],

                        links: {},
                    }
                },

                mounted() {
                    this.getProducts();
                },

                methods: {
                    getProducts() {
                        this.isLoading = true;

                        this.$axios.get("{{ route('shop.api.products.index') }}")
                            .then(response => {
                                this.isLoading = false;

                                this.products = response.data.data;

                                this.links = response.data.links;
                            }).catch(error => {
                                this.isLoading = false;

                                console.log(error);
                            });
                    },

                    loadMoreProducts() {
                        if (this.links.next) {
                            this.$axios.get(this.links.next).then(response => {
                                this.products = [...this.products, ...response.data.data];

                                this.links = response.data.links;
                            }).catch(error => {
                                console.log(error);
                            });
                        }
                    },
                },
            });
        </script>
    @endPushOnce
</x-shop::layouts>
