<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class OneCExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_c_checkauth_and_init_work_without_custom_1c_code(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');
        config()->set('integrations.one_c.file_limit', 5242880);

        $response = $this
            ->withServerVariables($this->oneCServer())
            ->get('/1c/exchange?type=catalog&mode=checkauth');

        $response->assertOk();
        $this->assertStringStartsWith("success\n", $response->getContent());

        $cookie = $this->extractSessionCookie($response);

        $initResponse = $this
            ->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=catalog&mode=init');

        $initResponse->assertOk();
        $this->assertStringContainsString('zip=no', $initResponse->getContent());
        $this->assertStringContainsString('file_limit=5242880', $initResponse->getContent());
    }

    public function test_one_c_catalog_import_creates_categories_products_and_prices(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $cookie = $this->authenticateOneC();

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=catalog&mode=init')
            ->assertOk();

        $importXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:00:00">
  <Классификатор>
    <Ид>classifier-1</Ид>
    <Наименование>Основной каталог</Наименование>
    <Группы>
      <Группа>
        <Ид>group-root</Ид>
        <Наименование>Профиля</Наименование>
        <Группы>
          <Группа>
            <Ид>group-child</Ид>
            <Наименование>Стеновые</Наименование>
          </Группа>
        </Группы>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Ид>catalog-1</Ид>
    <Наименование>Каталог MAJOR</Наименование>
    <Товары>
      <Товар>
        <Ид>product-guid-1</Ид>
        <Артикул>PF-001</Артикул>
        <Наименование>Профиль M</Наименование>
        <Описание>Тестовый товар из 1С</Описание>
        <БазоваяЕдиница НаименованиеКраткое="шт">шт</БазоваяЕдиница>
        <Группы>
          <Ид>group-child</Ид>
        </Группы>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML;

        $offersXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:01:00">
  <ПакетПредложений>
    <Ид>offers-1</Ид>
    <Наименование>Прайс</Наименование>
    <ТипыЦен>
      <ТипЦены>
        <Ид>price-type-1</Ид>
        <Наименование>Цена 1</Наименование>
      </ТипЦены>
    </ТипыЦен>
    <Предложения>
      <Предложение>
        <Ид>product-guid-1</Ид>
        <Цены>
          <Цена>
            <ИдТипаЦены>price-type-1</ИдТипаЦены>
            <ЦенаЗаЕдиницу>530.00</ЦенаЗаЕдиницу>
          </Цена>
        </Цены>
      </Предложение>
    </Предложения>
  </ПакетПредложений>
</КоммерческаяИнформация>
XML;

        $this->postExchangeFile($cookie, 'catalog', 'import.xml', $importXml)->assertOk();
        $this->postExchangeFile($cookie, 'catalog', 'offers.xml', $offersXml)->assertOk();

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->post('/1c/exchange?type=catalog&mode=import')
            ->assertOk();

        $this->assertDatabaseHas('categories', [
            'one_c_id' => 'group-child',
            'name' => 'Стеновые',
        ]);

        $product = Product::query()->where('one_c_id', 'product-guid-1')->firstOrFail();

        $this->assertSame('PF-001', $product->vendor_code);
        $this->assertSame('Профиль M', $product->title);
        $this->assertSame(530.0, (float) $product->price_from);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
        ]);
        $this->assertDatabaseHas('one_c_price_types', [
            'one_c_id' => 'price-type-1',
            'column_index' => 1,
        ]);
    }

    public function test_one_c_sale_query_exports_orders_and_success_marks_them_sent(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $user = User::factory()->create([
            'company' => 'ООО Объект',
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profiliya',
            'one_c_id' => 'group-child',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль M',
            'name' => 'Профиль M',
            'slug' => 'profil-m',
            'one_c_id' => 'product-guid-1',
            'vendor_code' => 'PF-001',
            'sort_order' => 0,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-20260407-00001',
            'status' => 'new',
            'payment_status' => 'pending',
            'customer_name' => 'Иван Клиент',
            'customer_company' => 'ООО Объект',
            'customer_email' => 'client@example.com',
            'customer_phone' => '+79991112233',
            'customer_delivery_address' => 'Саратов, Тестовая 1',
            'items_count' => 1,
            'subtotal_amount' => 1060,
            'total_amount' => 1060,
            'price_profile_name' => 'Цена 1',
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_slug' => $product->slug,
            'quantity' => 2,
            'price_label' => 'Цена 1',
            'unit_price' => 530,
            'line_total' => 1060,
            'measurement_value' => 'шт',
        ]);

        $cookie = $this->authenticateOneC();

        $queryResponse = $this
            ->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->get('/1c/exchange?type=sale&mode=query');

        $queryResponse->assertOk();
        $queryResponse->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('<КоммерческаяИнформация', $queryResponse->getContent());
        $this->assertStringContainsString('ORD-20260407-00001', $queryResponse->getContent());
        $this->assertStringContainsString('product-guid-1', $queryResponse->getContent());

        $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->post('/1c/exchange?type=sale&mode=success')
            ->assertOk();

        $this->assertNotNull($order->fresh()->one_c_exported_at);
    }

    private function authenticateOneC(): Cookie
    {
        $response = $this
            ->withServerVariables($this->oneCServer())
            ->get('/1c/exchange?type=catalog&mode=checkauth');

        $response->assertOk();

        return $this->extractSessionCookie($response);
    }

    private function postExchangeFile(Cookie $cookie, string $type, string $filename, string $content)
    {
        return $this->withServerVariables($this->oneCServer())
            ->withCookie($cookie->getName(), $cookie->getValue())
            ->call(
                'POST',
                '/1c/exchange',
                [
                    'type' => $type,
                    'mode' => 'file',
                    'filename' => $filename,
                ],
                [],
                [],
                $this->oneCServer(),
                $content,
            );
    }

    /**
     * @return array<string, string>
     */
    private function oneCServer(): array
    {
        return [
            'PHP_AUTH_USER' => 'site-exchange',
            'PHP_AUTH_PW' => 'secret-1c',
        ];
    }

    private function extractSessionCookie($response): Cookie
    {
        $cookieName = (string) config('session.cookie', 'laravel_session');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie;
            }
        }

        $this->fail('Session cookie was not returned by checkauth.');
    }
}
