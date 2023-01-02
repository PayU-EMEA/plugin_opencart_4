# Moduł PayU dla OpenCart wersja 4.x

**Jeżeli masz jakiekolwiek pytania lub chcesz zgłosić błąd zapraszamy do kontaktu z
naszym [wsparciem technicznym][ext1].**

## Spis treści

* [Cechy i kompatybilność](#cechy-i-kompatybilność)
* [Wymagania](#wymagania)
* [Instalacja](#instalacja)
* [Aktualizacja](#aktualizacja)
* [Konfiguracja](#konfiguracja)

## Cechy i kompatybilność

Moduł płatności PayU dodaje do OpenCart opcję płatności PayU i umożliwia:

* Utworzenie płatności
* Automatyczne odbieranie notyfikacji i zmianę statusu zamówienia

## Wymagania

**Ważne:** Moduł ta działa tylko z punktem płatności typu `REST API` (Checkout), jeżeli nie posiadasz jeszcze konta w
systemie PayU - [**Zarejestruj się**][ext6]

Do prawidłowego funkcjonowania modułu wymagane są następujące rozszerzenia PHP: [cURL][ext3] i [hash][ext4].

## Instalacja

## Konfiguracja

#### Parametry konfiguracyjne

| Parameter                      | Opis                                                                                                       |
|--------------------------------|------------------------------------------------------------------------------------------------------------|
| Id punktu płatności            | Identyfikator POS-a z systemu PayU                                                                         |
| Drugi klucz (MD5)              | Drugi klucz MD5 z systemu PayU                                                                             |
| Protokół OAuth - client_id     | client_id dla protokołu OAuth z systemu PayU                                                               |
| Protokół OAuth - client_secret | client_secret dla protokołu OAuth z systemu PayU                                                           |
| Tryb testowy (Sandbox)         | Określa czy transakcje będą procesowane przez system testowy PayU (Sandbox)                                |
| Strefa Geo                     | Strefa Geo, dla której metoda płatności PayU dostępna w sklepie na liście płatności.                       |
| Status                         | Określa czy metoda płatności PayU będzie dostępna w sklepie na liście płatności.                           |
| Kolejność                      | Określa na której pozycji ma być wyświetlana metoda płatności PayU dostępna w sklepie na liście płatności. |

#### Parametry statusów

Określa relacje pomiędzy statusami zamówienia w PayU a statusami zamówienia w OpenCart.

<!--LINKS-->

<!--external links:-->

[ext1]: https://www.payu.pl/pomoc

[ext1]: https://github.com/PayU/plugin_opencart_2

[ext2]: https://github.com/PayU/plugin_opencart_2/tree/opencart_2_2

[ext3]: http://php.net/manual/en/book.curl.php

[ext4]: http://php.net/manual/en/book.hash.php

[ext5]: https://github.com/PayU/plugin_opencart_5

[ext6]: https://www.payu.pl/oferta-handlowa

<!--images:-->
