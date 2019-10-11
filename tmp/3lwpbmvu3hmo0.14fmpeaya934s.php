<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>EZN - Rejestracja</title>
    <link rel="stylesheet" href="ui/css/bootstrap.min.css">
    <link rel="stylesheet" href="./ui/css/loginRegister.css">    
    <style>
        .error{ 
            color: red;
        }
    </style>
</head>
<body>
        <div class="container">
                <div class="row box">
                    <div class="col-md-4 col-sm-8 col-xs-12 text-center inner-box">
                        <form method="POST" action="register" >
                            <div class="form-group text-left">
                                <label for="username">Login</label>
                                <input name="username" class="form-control" placeholder="login" maxlength="<?= ($max_login_len) ?>" autocomplete="off"><br>
                            </div>
                            <div class="form-group text-left">
                                <label for="password">Hasło</label>
                                <input type="password" class="form-control" name="password" placeholder="password" maxlength="<?= ($max_passw_len) ?>"><br>                        
                            </div>
                            <button type="submit" class="btn btn-primary">Zarejestruj się!</button>
                        </form>
                        <a href="login">Powrót do logowania</a>
                        <div class="error">
                            <?= ($error1)."
" ?>
                            <?= ($error2)."
" ?>
                            <?= ($error3)."
" ?>
                            <?= ($error4)."
" ?>
                            <?= ($error5)."
" ?>
                        </div>
                    </div>
                </div>
            </div>
</body>
</html>