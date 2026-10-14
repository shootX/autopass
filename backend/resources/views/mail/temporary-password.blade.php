<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <title>autopass</title>
</head>
<body style="font-family: Arial, sans-serif; color: #14482F;">
    <p>{{ $accountName }}</p>
    <p>შესვლის სახელი: {{ $login }}</p>
    <p>დროებითი პაროლი: {{ $temporaryPassword }}</p>
    <p>პაროლი მოქმედებს {{ $hours }} საათი. პირველი შესვლის შემდეგ შეცვალეთ იგი.</p>
</body>
</html>
