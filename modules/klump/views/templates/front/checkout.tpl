{*
* Klump
*}
{if isset($gateway_chosen) && $gateway_chosen == 'klump'}
<form method="POST" id="klump_form">
</form>
<div id='klump__checkout'></div>
<script src="https://js.useklump.com/klump.js"></script>
<script type="text/javascript">
    const cartItems = {$items|unescape: "html" nofilter};
    const dataInfo = {
        amount: {$amount},
        shipping_fee: {$shipping_fee},
        currency: '{$currency}',
        merchant_reference: '{$merchant_reference}',
        first_name: '{$customer_first_name}',
        last_name: '{$customer_last_name}',
        
        email: '{$customer_email}',
        meta_data: {
            customer: '{$customer}',
            email: '{$customer_email}',
            klump_plugin_source: 'prestashop',
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
            if (status === 'successful' && trxReference && type === 'SUCCESS') {
                location.href = '{$redirect_url}?reference=' + trxReference;
            }
        },
        onError: (data) => {
            console.log(data);
        },
        onLoad: (data) => {
            console.log(data);
        },
        onOpen: (data) => {
            console.log(data);
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