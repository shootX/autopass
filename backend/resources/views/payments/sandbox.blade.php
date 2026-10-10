<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>სატესტო გადახდა</title>
    <style>
        body { margin: 0; background: #f4f7f5; color: #143028; font: 16px/1.4 system-ui, sans-serif; }
        main { max-width: 420px; margin: 32px auto; padding: 24px; background: #fff; border-radius: 16px; }
        p { margin: 0 0 8px; color: #5d6b64; }
        b { display: block; font-size: 32px; margin-bottom: 18px; }
        label { display: block; margin: 12px 0 6px; font-size: 13px; font-weight: 700; }
        input { width: 100%; box-sizing: border-box; height: 46px; border: 1px solid #d7e0db; border-radius: 10px; padding: 0 12px; font: inherit; }
        .row { display: flex; gap: 10px; }
        .row > div { flex: 1; }
        .err { color: #b42318; font-weight: 700; }
        .wallets { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
        .wallet, .pay, .no { height: 48px; border: 0; border-radius: 12px; font: inherit; font-weight: 700; cursor: pointer; }
        .apple { background: #000; color: #fff; }
        .google { background: #fff; color: #143028; border: 1px solid #d7e0db; }
        .pay { width: 100%; margin-top: 16px; background: #14482f; color: #fff; }
        .no { width: 100%; margin-top: 8px; background: #eef2ef; color: #143028; }
        .hint { margin-top: 10px; font-size: 13px; }
    </style>
</head>
<body>
<main>
    <p>TBC sandbox</p>
    <h1>სატესტო გადახდა</h1>
    <p>{{ $payment->type === 'points' ? 'ქულები' : 'პაკეტი' }}</p>
    <b>{{ number_format((float) $payment->amount, 2, '.', '') }} ₾</b>
    @if (!empty($error))
        <p class="err">{{ $error }}</p>
    @endif

    <form id="pay" method="post" action="{{ route('payment.sandbox.confirm') }}">
        @csrf
        <input type="hidden" name="order" value="{{ $payment->merchant_payment_id }}">
        <input type="hidden" name="decision" id="decision" value="pay">
        <input type="hidden" name="method" id="method" value="card">
        <input type="hidden" name="last4" id="last4" value="">

        <label for="pan">ბარათის ნომერი</label>
        <input id="pan" inputmode="numeric" autocomplete="cc-number" maxlength="19" placeholder="4111 1111 1111 1111">
        <div class="row">
            <div>
                <label for="exp">ვადა</label>
                <input id="exp" inputmode="numeric" autocomplete="cc-exp" maxlength="5" placeholder="12/28">
            </div>
            <div>
                <label for="cvv">CVV</label>
                <input id="cvv" inputmode="numeric" autocomplete="cc-csc" maxlength="3" placeholder="123">
            </div>
        </div>
        <p class="hint">სატესტო ბარათი: 4111 1111 1111 1111, ნებისმიერი მომავალი ვადა, CVV 123</p>
        <button class="pay" type="submit">ბარათით გადახდა</button>
    </form>

    <div class="wallets">
        <button class="wallet apple" type="button" id="apple">Apple Pay</button>
        <button class="wallet google" type="button" id="google">Google Pay</button>
        <button class="no" type="button" id="decline">უარი</button>
    </div>
</main>
<script>
    const form = document.getElementById("pay");
    const pan = document.getElementById("pan");
    const exp = document.getElementById("exp");
    const cvv = document.getElementById("cvv");

    pan.addEventListener("input", () => {
        const digits = pan.value.replace(/\D/g, "").slice(0, 16);
        pan.value = digits.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
    });
    exp.addEventListener("input", () => {
        const digits = exp.value.replace(/\D/g, "").slice(0, 4);
        exp.value = digits.length > 2 ? digits.slice(0, 2) + "/" + digits.slice(2) : digits;
    });

    function luhn(num) {
        let sum = 0;
        let alt = false;
        for (let i = num.length - 1; i >= 0; i--) {
            let n = parseInt(num[i], 10);
            if (alt) {
                n *= 2;
                if (n > 9) n -= 9;
            }
            sum += n;
            alt = !alt;
        }
        return sum % 10 === 0;
    }

    function cardOk() {
        const digits = pan.value.replace(/\D/g, "");
        const parts = exp.value.split("/");
        const month = parseInt(parts[0], 10);
        const year = 2000 + parseInt(parts[1], 10);
        const now = new Date();
        const future = year > now.getFullYear() || (year === now.getFullYear() && month >= now.getMonth() + 1);
        return digits.length === 16 && luhn(digits) && month >= 1 && month <= 12 && future && /^\d{3}$/.test(cvv.value);
    }

    form.addEventListener("submit", (event) => {
        if (document.getElementById("method").value !== "card") return;
        if (!cardOk()) {
            event.preventDefault();
            alert("ბარათის მონაცემები არასწორია");
            return;
        }
        document.getElementById("last4").value = pan.value.replace(/\D/g, "").slice(-4);
        pan.value = "";
        cvv.value = "";
    });

    function wallet(method) {
        document.getElementById("method").value = method;
        document.getElementById("decision").value = "pay";
        form.submit();
    }
    document.getElementById("apple").addEventListener("click", () => wallet("apple"));
    document.getElementById("google").addEventListener("click", () => wallet("google"));
    document.getElementById("decline").addEventListener("click", () => {
        document.getElementById("method").value = "card";
        document.getElementById("decision").value = "decline";
        form.submit();
    });
</script>
</body>
</html>
