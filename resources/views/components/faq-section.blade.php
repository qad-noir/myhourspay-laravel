@props([
    'items' => [],
    'eyebrow' => 'FAQ',
    'title' => 'Have more questions?',
    'description' => 'Find practical answers about tracking hours, reports, plans and working with your team.',
    'supportTitle' => "Can't find answers?",
    'supportCopy' => 'We are here to help. Get in touch with our support team for a clear answer.',
    'supportLabel' => 'Contact support',
    'supportHref' => 'mailto:'.config('site.contact.email'),
])

<section class="public-container public-faq grid grid-cols-1 gap-10 pb-20 pt-28 lg:grid-cols-[.9fr_1.1fr] lg:gap-[72px] lg:pb-[104px] lg:pt-36" data-faq-group>
    <div class="flex flex-col items-start">
        <span class="inline-flex items-center gap-2 rounded-full border border-[var(--brand-ink)] px-3.5 py-2 text-xs font-bold text-[var(--brand-ink)]"><span aria-hidden="true" class="text-base leading-none">✣</span>{{ $eyebrow }}</span>
        <h2 class="mb-[18px] mt-[22px] max-w-[500px] font-[Manrope] text-[clamp(34px,4.2vw,54px)] font-extrabold leading-[1.05] tracking-[-.055em] text-[var(--brand-ink)]">{{ $title }}</h2>
        <p class="m-0 max-w-[520px] text-base leading-[1.7] text-[var(--brand-muted)]">{{ $description }}</p>
        <div class="mt-[38px] w-full max-w-[620px] rounded-[17px] border border-[#ddd7df] p-7 shadow-[0_5px_12px_rgba(23,20,33,.05)] lg:mt-auto">
            <h3 class="mb-[13px] font-[Manrope] text-2xl font-extrabold tracking-[-.035em] text-[var(--brand-ink)]">{{ $supportTitle }}</h3>
            <p class="mb-[23px] max-w-[450px] text-sm leading-[1.65] text-[var(--brand-muted)]">{{ $supportCopy }}</p>
            <a class="inline-flex items-center gap-2.5 rounded-full border-2 border-[#8f8a91] bg-[var(--brand-ink)] px-4 py-2 text-[13px] font-bold text-white shadow-[inset_0_0_0_2px_var(--brand-ink),0_0_0_2px_white] transition-colors hover:bg-[var(--brand-violet)] focus-visible:outline focus-visible:outline-3 focus-visible:outline-[var(--brand-orange)] focus-visible:outline-offset-3" href="{{ $supportHref }}">{{ $supportLabel }} <span aria-hidden="true" class="text-[17px] leading-none">↗</span></a>
        </div>
    </div>
    <div class="grid content-start gap-[18px] pt-0.5 max-lg:gap-3">
        @foreach($items as $item)
            <details class="group overflow-hidden rounded-[10px] bg-[#f5f4f4] open:bg-[#f1f0f0]" @if($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-[18px] px-[22px] py-[22px] font-[Manrope] text-[15px] font-bold text-[var(--brand-ink)] focus-visible:outline focus-visible:outline-3 focus-visible:outline-[var(--brand-orange)] focus-visible:outline-offset-3">
                    <span>{{ $item['question'] }}</span>
                    <span class="grid h-[30px] w-[30px] flex-none place-items-center rounded-[9px] bg-[#dedddd] text-lg font-normal leading-none text-[var(--brand-ink)] transition-all group-open:rotate-180 group-open:bg-[var(--brand-ink)] group-open:text-white" aria-hidden="true">↓</span>
                </summary>
                <div class="px-[22px] pb-[22px]"><p class="m-0 max-w-[600px] text-sm leading-[1.7] text-[#706d70]">{{ $item['answer'] }}</p></div>
            </details>
        @endforeach
    </div>
</section>
