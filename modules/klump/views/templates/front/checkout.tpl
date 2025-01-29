{*
* Klump
*}
{if isset($gateway_chosen) && $gateway_chosen == 'klump'}
<form method="POST" id="klump_form" action="{$redirect_url}">
    <input type="hidden" name="amount" value="{$amount}" />
    <input type="hidden" name="email" value="{$email}" />
</form>
<div id='klump__checkout'></div>
{*<script src="https://js.useklump.com/klump.js"></script>*}
{*<script src="https://staging-new-js.useklump.com/klump.js"></script>*}
<script src="https://new-js.useklump.com/klump.js"></script>
<script type="text/javascript">
    const cartItems = {$items|unescape: "html" nofilter};
    const dataInfo = {
        amount: {$amount},
        shipping_fee: {$shipping_fee},
        tax: {$tax},
        currency: '{$currency}',
        merchant_reference: '{$merchant_reference}',
        first_name: '{$customer_first_name}',
        last_name: '{$customer_last_name}',
        redirect_url: '{$redirect_url}',
        email: '{$customer_email}',
        meta_data: {
            customer: '{$customer}',
            email: '{$customer_email}',
            klump_plugin_source: 'prestashop',
            klump_plugin_version: '0.1.0',
            customer_address: '{$customer_address}'
        },
        items: cartItems
    };
    {if isset($customer_phone)}
        dataInfo.phone = '{$customer_phone}'
    {/if}
	const payload = {
        publicKey: '{$merchant_public_key}',
        data: dataInfo,
        onSuccess: (data) => {
            const trxReference = data.data.data.data.reference;
            const  { status } = data.data.data;
            const { type } = data.data;

            // Get the form element by its ID
            const form = document.getElementById('klump_form');

            // Create a new input element
            const newField = document.createElement('input');

            // Set the input element's attributes
            newField.setAttribute('type', 'hidden');
            newField.setAttribute('name', 'reference');
            newField.setAttribute('value', trxReference);

            // Append the new input element to the form
            form.appendChild(newField);

            if (status === 'successful' && trxReference && type === 'SUCCESS') {
                $( "#klump_form" ).submit();
            }
        },
        onError: (data) => {
            console.error(data);
        },
        onLoad: (data) => {
            // console.log(data);
        },
        onOpen: (data) => {
            // console.log(data);
        },
        onClose: (data) => {
            console.log(data);
        }
    }
    /**
    * Event listener is simulated here. This way, the checkout popup is activated with
    * almost no action from the user.
    */
    const klumpBtn = document.getElementById('klump__checkout');

    klumpBtn.addEventListener('click', function(e) {
        const klump = new Klump(payload);
    });

    const simulatedBtnClick = document.createEvent('MouseEvents');

    simulatedBtnClick.initEvent(
        'click', /* Event type */
        true, /* bubbles */
        true, /* cancelable */
        document.defaultView, /* view */
        0, /* detail */
        0, /* screenx */
        0, /* screeny */
        0, /* clientx */
        0, /* clienty */
        false, /* ctrlKey */
        false, /* altKey */
        false, /* shiftKey */
        0, /* metaKey */
        null, /* button */
        null /* relatedTarget */
    );

    // Automatically click after 1 second
    setTimeout(function() {
        klumpBtn.dispatchEvent(simulatedBtnClick);
    }, 1000);
</script>
{/if}