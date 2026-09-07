@props([
    'items' => [],
    'eyebrow' => 'FAQ',
    'title' => 'Have more questions?',
    'description' => 'Find practical answers about tracking hours, reports, plans and working with your team.',
    'supportTitle' => "Can't find answers?",
    'supportCopy' => 'We are here to help. Get in touch with our support team for a clear answer.',
    'supportLabel' => 'Contact support',
    'supportHref' => 'mailto:'.config('site.contact.email'),
    'standalone' => false,
])

<section class="public-faq bg-white font-body text-[var(--brand-ink)] {{ $standalone ? 'pt-32 lg:pt-44' : 'pt-16 lg:pt-24' }} pb-16 lg:pb-24" data-faq-group aria-labelledby="faq-heading">
    <div class="public-container grid grid-cols-1 gap-x-16 gap-y-10 lg:grid-cols-2 xl:gap-x-24">
    <div>
        <span class="inline-flex items-center gap-2 rounded-full border border-[var(--brand-ink)] px-3.5 py-2 text-sm font-medium leading-5"><span aria-hidden="true" class="text-lg leading-none">✣</span>{{ $eyebrow }}</span>
        @if($standalone)
            <h1 id="faq-heading" class="mb-5 mt-5 font-heading text-3xl font-semibold leading-tight tracking-tight sm:text-[38px]">{{ $title }}</h1>
        @else
            <h2 id="faq-heading" class="mb-5 mt-5 font-heading text-3xl font-semibold leading-tight tracking-tight sm:text-[38px]">{{ $title }}</h2>
        @endif
        <p class="m-0 text-base leading-7 text-[var(--brand-muted)]">{{ $description }}</p>
    </div>
    <div class="grid content-start gap-5 lg:col-start-2 lg:row-span-2 lg:row-start-1">
        @foreach($items as $item)
            <details name="public-faq" class="group rounded-lg bg-neutral-100" @if($loop->first) open @endif>
                <summary class="flex min-h-[66px] cursor-pointer list-none items-center justify-between gap-4 rounded-lg px-5 py-[18px] text-base font-medium leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--brand-orange)] focus-visible:outline-offset-2 sm:px-6">
                    <span>{{ $item['question'] }}</span>
                    <span data-faq-indicator class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-lg bg-neutral-200 transition-colors duration-300 group-open:bg-[var(--brand-ink)] group-open:text-white motion-reduce:transition-none" aria-hidden="true"><svg class="h-4 w-4 transition-transform duration-300 group-open:rotate-180 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m-7-7 7 7 7-7"/></svg></span>
                </summary>
                <div class="px-5 pb-5 sm:px-6"><p class="m-0 text-base leading-7 text-[var(--brand-muted)]">{{ $item['answer'] }}</p>
                    @if(!empty($item['links']))
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">@foreach($item['links'] as $link)<a class="text-sm font-medium underline underline-offset-4 hover:text-[var(--brand-orange)]" href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
    <div class="self-end rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm lg:col-start-1 lg:row-start-2 sm:p-7">
        <h3 class="mb-3 font-heading text-2xl font-semibold leading-tight tracking-tight">{{ $supportTitle }}</h3>
        <p class="mb-6 text-base leading-7 text-[var(--brand-muted)]">{{ $supportCopy }}</p>
        <a class="inline-flex min-h-11 items-center gap-2 rounded-full border-2 border-neutral-400 bg-[var(--brand-ink)] px-5 py-2 text-base font-semibold text-white ring-1 ring-neutral-300 transition-colors hover:bg-neutral-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--brand-orange)] focus-visible:outline-offset-4" href="{{ $supportHref }}">{{ $supportLabel }} <svg class="h-4 w-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18 18 6M6 6h12v12"/></svg></a>
    </div>
    </div>
</section>
