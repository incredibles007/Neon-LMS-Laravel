@component('mail::message')
#Hello {{auth()->user()->name}}

We have successfully received your request for following:<br>
Order Reference No. {{$content['reference_no']}}
@foreach($content['items'] as $item)
#{{$item['number']}}. {{$item['name']}} {{'$'.$item['price']}}
@endforeach

#Total Amount : ${{$content['total']}}

Our representatives will contact you soon regarding order payments.
Happy Shopping.


Thanks,<br>
{{ config('app.name') }}
@endcomponent
