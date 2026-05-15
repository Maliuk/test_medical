# Тестовий проект: Імпорт медичних даних (CSV)

Цей проект демонструє імпорт даних з CSV файлу до MongoDB та ElasticSearch з використанням Yii2 та черг (RabbitMQ).

## Технологічний стек
- **PHP 8.2+** (Yii 2 Basic)
- **MySQL 8.0** (для стандартних даних)
- **MongoDB** (для зберігання сирих даних імпорту)
- **ElasticSearch 8.13** (для пошуку та звітів)
- **RabbitMQ** (для черг)
- **Docker & Docker Compose**

---

## Розгортання проекту через Docker

1. **Запустіть контейнери:**
```bash
   docker compose up -d
```

2. **Встановіть залежності Composer:**
```bash
   docker compose exec php composer install
```

3. **Запустіть міграції бази даних:**
```bash
   docker compose exec php php yii migrate --interactive=0
```

---

## Запуск тестів

Для запуску всіх тестів (Unit, Functional, Acceptance):
```bash
docker compose exec php composer tests
```

---

## Імпорт CSV даних

Файл для імпорту за замовчуванням: `medical.csv` (має бути в корені проекту).

### 1. Прямий імпорт (без черги)
Дані обробляються послідовно в межах одного консольного процесу.
```bash
docker compose exec php php yii import/csv
```
Або з вказанням іншого файлу:
```bash
docker compose exec php php yii import/csv custom_file.csv
```

### 2. Імпорт через чергу (асинхронно)
Команда розбиває файл на частини (чанки) та додає завдання в чергу RabbitMQ.
```bash
docker compose exec php php yii import/csv-queue
```

#### Запуск воркера для обробки черги
Щоб завдання з черги почали виконуватися, необхідно запустити слухача:
```bash
docker compose exec php php yii queue/listen
```
*Рекомендується запускати в окремому вікні терміналу.*

---

## Використання через веб-інтерфейс

Окрім консольних команд, доступний веб-інтерфейс для керування імпортом та перегляду звітів.

### 1. Завантаження CSV файлу
Перейдіть за посиланням: [http://localhost:8000/index.php?r=import](http://localhost:8000/index.php?r=import)
- Виберіть файл для завантаження.
- Відмітьте чекбокс "Використовувати чергу", якщо бажаєте обробити файл через RabbitMQ (потребує запущеного воркера `queue/listen`).
- Після завершення ви побачите повідомлення з результатом обробки.

### 2. Перегляд звіту
Перейдіть за посиланням: [http://localhost:8000/index.php?r=import/report](http://localhost:8000/index.php?r=import/report)
Тут відображається таблиця зі статистикою по регіонах та продуктах (дані підтягуються з ElasticSearch).

---

## Додаткові консольні команди

- **Перегляд статистики ElasticSearch:**
  ```bash
  docker compose exec php php yii import/stats
  ```

- **Генерація звіту (Групування за регіоном та продуктом):**
  ```bash
  docker compose exec php php yii import/report
  ```

- **Очищення даних:**
  Очищення індексу ElasticSearch:
  ```bash
  docker compose exec php php yii import/clear
  ```
  Очищення MongoDB (через контейнер):
  ```bash
  docker compose exec mongodb mongosh yii2basic --eval "db.invoices.drop()"
  ```

---

## Сервіси та посилання

Після запуску Docker, доступні наступні сервіси:

- **Веб-додаток:** [http://localhost:8000](http://localhost:8000)
- **RabbitMQ Management:** [http://localhost:15672](http://localhost:15672) (логін/пароль: `guest`/`guest`)
- **ElasticSearch:** [http://localhost:9200](http://localhost:9200)
- **MongoDB:** `localhost:27017`
- **MySQL:** `localhost:3306` (база: `yii2basic`, root: `root`)

---

## Корисні команди Docker

- **Зупинити проект:** `docker compose stop`
- **Переглянути логи PHP:** `docker compose logs -f php`
- **Зайти в контейнер:** `docker compose exec php bash`
