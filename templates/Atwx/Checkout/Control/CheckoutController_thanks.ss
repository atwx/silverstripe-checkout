<div class="checkout checkout--thanks">
    <% if $Order.isCompleted %>
        <h1><%t Atwx\Checkout.ThanksTitle 'Vielen Dank!' %></h1>
        <p><%t Atwx\Checkout.ThanksText 'Ihre Zahlung war erfolgreich. Bestellnummer:' %> <strong>$Order.OrderNumber</strong></p>
        <p><%t Atwx\Checkout.ThanksAmount 'Betrag:' %> $Order.TotalFormatted</p>
    <% else %>
        <h1><%t Atwx\Checkout.PendingTitle 'Zahlung wird bestätigt' %></h1>
        <p><%t Atwx\Checkout.PendingText 'Ihre Zahlung ist noch nicht bestätigt. Bestellnummer:' %> <strong>$Order.OrderNumber</strong></p>
    <% end_if %>
</div>
