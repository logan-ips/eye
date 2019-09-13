@if (count(config('eyewitness.application_domains')))
    @foreach(config('eyewitness.application_domains') as $domain)
        <div class="bg-white border-t border-b sm:rounded sm:border shadow-inner mb-12">
            <div class="flex mb-8 border-b border-grey-light pb-4">
                <div class="flex-none bg-brand bg-circuit bg-md ml-4 w-16 h-16 -mt-6 rounded shadow-lg">
                    <div class="h-full flex justify-center items-center">
                        @eyewitness_svg('l-security', 'svgcolor-white')
                    </div>
                </div>

                <h1 class="font-hairline ml-4 mt-2 text-2xl">SSL: {{ $domain }}</h1>

            </div>

            @if ($ssl = \Eyewitness\Eye\Repo\History\Ssl::where('meta', $domain)->first())
                @if ($ssl->record['revoked'] || $ssl->record['expires_soon'] || (! $ssl->record['valid']))
                    <div class="flex items-end justify-center w-full items-center pb-8">
                        <div class="f-modal-alert pr-8">
                            <div class="f-modal-icon f-modal-warning scaleWarning">
                                <span class="f-modal-body pulseWarningIns"></span>
                                <span class="f-modal-dot pulseWarningIns"></span>
                            </div>
                        </div>
                        <p class="text-2xl text-grey-darker font-medium">Your certificate has issues!</p>
                    </div>
                @else
                    <div class="flex items-end justify-center w-full items-center pb-8">
                        <div class="f-modal-alert pr-8">
                            <div class="f-modal-icon f-modal-success animate scaleWarning">
                                <span class="f-modal-line f-modal-tip animateSuccessTip"></span>
                                <span class="f-modal-line f-modal-long animateSuccessLong"></span>
                                <div class="f-modal-placeholder"></div>
                                <div class="f-modal-fix"></div>
                            </div>
                        </div>
                        <p class="text-2xl text-grey-darker font-medium">Your SSL certificate is valid</p>
                    </div>
                @endif

                <div class="px-6 pb-4">
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Certificate status</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                <p class="text-grey-darker ml-4">
                                    @if ($ssl->record['revoked'])
                                        <span class="shadow uppercase font-semibold rounded text-xs text-white bg-red py-1 px-2 mr-1">Revoked</span>
                                    @elseif (! $ssl->record['valid'])
                                        <span class="shadow uppercase font-semibold rounded text-xs text-white bg-red py-1 px-2 mr-1">Invalid</span>
                                    @else
                                        <span class="shadow uppercase font-semibold rounded text-xs text-white bg-green py-1 px-2 mr-1">Valid</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Days until expiry</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                <span class="shadow uppercase font-semibold rounded text-xs text-white {{ $ssl->record['expires_soon'] ? 'bg-orange' : 'bg-green' }} py-1 px-2 mr-1 ml-4">{{ round(($ssl->record['valid_to']-time())/60/60/24) }} days</span>
                            </div>
                        </div>
                    </div>
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Certificate grade</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                @if (\Illuminate\Support\Str::startsWith($ssl->record['grade'], 'A'))
                                    <span class="shadow uppercase font-semibold rounded text-xs text-white bg-green py-1 px-2 mr-1 ml-4">{{ $ssl->record['grade'] }}</span>
                                @elseif (\Illuminate\Support\Str::startsWith($ssl->record['grade'], 'B'))
                                    <span class="shadow uppercase font-semibold rounded text-xs text-white bg-orange py-1 px-2 mr-1 ml-4">{{ $ssl->record['grade'] }}</span>
                                @else
                                    <span class="shadow uppercase font-semibold rounded text-xs text-white bg-red py-1 px-2 mr-1 ml-4">{{ $ssl->record['grade'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Certificate dates</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                <p class="text-grey-darker ml-4">{{ date('Y-m-d', $ssl->record['valid_from']) }} to {{ date('Y-m-d', $ssl->record['valid_to']) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Certificate issuer</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                <p class="text-grey-darker ml-4">{{ $ssl->record['issuer'] }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="pl-1 pb-3 pt-3">
                        <div class="flex">
                            <div class="w-1/2 md:w-1/4 text-right">
                                <p class="block tracking-wide text-grey text-right mr-1">Full report</p>
                            </div>
                            <div class="w-1/2 md:w-3/4">
                                <a href="{{ $ssl->record['results_url'] }}" class="ml-4 -mt-1 text-white font-bold pr-4 py-2 rounded inline-flex items-center no-underline shadow-md cursor-pointer hover:bg-brand-light hover:shadow-lg btn-pop bg-brand text-xs" rel="noopener noreferrer" target="_blank">@eyewitness_svg('link-67', 'svgcolor-white h-4 w-4 mx-2') View report by htbridge.com</a>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="mb-4">
                        @eyewitness_svg('wifi-off')
                    </div>
                    <p class="text-2xl text-grey-darker font-medium mb-4">No SSL records found yet</p>
                    <p class="text-grey max-w-sm mx-auto mb-6">Your domains are scheduled to be checked in a short time (usually within an hour).</p>
                    <p class="text-grey max-w-sm mx-auto mb-6">Your scheduler needs to be running for this check to take effect, as we need to run a few different tasks.</p>
                </div>
            @endif
        </div>
    @endforeach
@else
    <div class="bg-white border-t border-b sm:rounded sm:border shadow-inner">
        <div class="flex mb-8 border-b border-grey-light pb-4">
            <div class="flex-none bg-brand bg-circuit bg-md ml-4 w-16 h-16 -mt-6 rounded shadow-lg">
                <div class="h-full flex justify-center items-center">
                    @eyewitness_svg('l-security', 'svgcolor-white')
                </div>
            </div>
            <h1 class="font-hairline ml-4 mt-2 text-2xl">SSL</h1>
        </div>
        <div class="text-center px-6 pb-8">
            @eyewitness_tutorial('Eyewitness keeps an eye on your application SSL using the HtBridge API. If any changes are detected, you will receive an alert.')

            <div class="py-8">
                <div class="mb-4">
                    @eyewitness_svg('wifi-off')
                </div>
                <p class="text-2xl text-grey-darker font-medium mb-4">SSL monitoring not active</p>
                <p class="text-grey max-w-sm mx-auto mb-6">You need to set your <span class="code">application_domains</span> in the <span class="code">config/eyewitness.php</span> config file to activate SSL monitoring.</p>
            </div>
        </div>
    </div>
@endif

