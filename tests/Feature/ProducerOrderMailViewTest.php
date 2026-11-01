<?php

use Guixa\Ordering\Infrastructure\Membership\ProducerNotifierMail;

it('renders the producer order as escaped paragraphs and product lines', function () {
    $html = view('mail.producer-order', [
        'heading' => 'Pickup <Friday> & friends',
        'formattedTotals' => [
            ['name' => '<Tomàquet>', 'quantity' => '12.345,678', 'unit' => 'kg & box'],
            ['name' => 'Bread', 'quantity' => '2,000', 'unit' => 'unit'],
        ],
    ])->render();

    expect(rtrim($html))->toBe('<p>Pickup &lt;Friday&gt; &amp; friends</p><p>- &lt;Tomàquet&gt;: 12.345,678 kg &amp; box<br>- Bread: 2,000 unit</p>');
});

it('keeps the producer subject, translated heading, decimal text and raw totals', function () {
    app()->setLocale('ca');
    $totals = [
        ['name' => 'Olives', 'quantity' => '1000000.000', 'unit' => 'kg'],
        ['name' => 'Oil', 'quantity' => '-12.3400', 'unit' => 'l'],
        ['name' => '<Jam>', 'quantity' => '<unknown>', 'unit' => '<jar>'],
    ];
    $mail = new ProducerNotifierMail(new DateTimeImmutable('2026-11-05'), $totals);

    expect($mail->envelope()->subject)->toBe('Comanda de Guixa per al 05/11/2026')
        ->and(rtrim($mail->render()))->toBe('<p>Comanda per a la recollida del 05/11/2026:</p><p>- Olives: 1.000.000,000 kg<br>- Oil: −12,3400 l<br>- &lt;Jam&gt;: &lt;unknown&gt; &lt;jar&gt;</p>')
        ->and($mail->totals)->toBe($totals);
});

it('renders empty totals without adding a product or household line', function () {
    app()->setLocale('ca');
    $mail = new ProducerNotifierMail(new DateTimeImmutable('2026-11-05'), []);

    expect(rtrim($mail->render()))->toBe('<p>Comanda per a la recollida del 05/11/2026:</p><p></p>');
});
