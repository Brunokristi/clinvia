@extends('emails.layout', [
    'title' => $subject,
])

@section('content')
    <h1 style="margin: 0 0 16px 0; font-size: 22px; line-height: 1.3; font-weight: 700; color: #C17979;">
        {{ $subject }}
    </h1>

    <p style="margin: 0 0 16px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        Dobrý deň,
    </p>

    <div style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
        {!! nl2br(e($bodyText)) !!}
    </div>

    @if($branchName)
        <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #A75A5A;">
            S pozdravom,<br>
            {{ $branchName }}
        </p>
    @endif

    @if(filled($originalMessage ?? null))
        <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #F3DADA;">
            <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #A75A5A;">
                Vaša pôvodná správa
            </p>

            <div style="padding: 16px; border-radius: 12px; background-color: #FFF7F7; color: #A75A5A; font-size: 14px; line-height: 1.6;">
                {!! nl2br(e($originalMessage)) !!}
            </div>
        </div>
    @endif
@endsection