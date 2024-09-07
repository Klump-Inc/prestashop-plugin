{*
* Klump
*}
{if isset($gateway_chosen) && $gateway_chosen == 'klump'}
<div id='klump__checkout'></div>
<script src="https://js.useklump.com/klump.js"></script>
<script type="text/javascript">
	const payload = {
        publicKey: '{$merchant_public_key}',
        data: {
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
            },
            items: [
                {
                    image_url:
                        'https://s3.amazonaws.com/uifaces/faces/twitter/ladylexy/128.jpg',
                    item_url: 'https://www.paypal.com/in/webapps/mpp/home',
                    name: 'Awesome item',
                    unit_price: 2000,
                    quantity: 2,
                }
            ]
        },
        onSuccess: (data) => {
            console.log('html onSuccess will be handled by the merchant');
            console.log(data);
            ok = data;
            return data;
        },
        onError: (data) => {
            console.log('html onError will be handled by the merchant');
            console.log(data);
        },
        onLoad: (data) => {
            console.log('html onLoad will be handled by the merchant');
            console.log(data);
        },
        onOpen: (data) => {
            console.log('html OnOpen will be handled by the merchant');
            console.log(data);
        },
        onClose: (data) => {
            console.log('html onClose will be handled by the merchant');
            console.log(data);
        }
    }
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