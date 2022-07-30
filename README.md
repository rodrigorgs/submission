API para submissão de exercícios a partir de sites estáticos.

## Ambiente de desenvolvimento

Instale as dependências:

```
composer install
```

Inicie o MySQL:

```bash
docker stop mysql-db; \
docker run --rm --name mysql-db -p 3306:3306 \
    -e MYSQL_ROOT_PASSWORD=root \
    -e MYSQL_DATABASE=db \
    -d mysql
```

Crie o banco de dados:

```
php create-db.php
```

Inicie a aplicação:

```bash
php -S localhost:8080 .
```

Se precisar acessar o banco de dados:

```bash
mysql --host=0.0.0.0 -u root --password
mysql> USE db;
mysql> SHOW TABLES;
```

Atenção: em alguns ambientes, é necessário copiar o arquivo `.htaccess`.
## Usando a API


### login

Envie uma requisição POST para `/login.php` com o seguinte corpo:

```
{
  "username": "seu-nome-de-usuario",
  "password": "sua-senha"
}
```

Você receberá de volta um token JWT.

### submit

Para submeter uma resposta, envie uma requisição POST para `/submit.php` com o seguinte corpo:

```
{
  "answers": ["resposta1", "resposta2", "etc"]
}
```

Além disso você deve adicionar o seguinte cabeçalho:

```
Authorization: Bearer O-SEU-TOKEN-JWT
```

O valor do cabeçalho `Referer` será usado para determinar a prova para a qual se estão submetendo as respostas.
