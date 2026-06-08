<div align="center">

<img src="https://placehold.co/1200x300/064e3b/ffffff?text=🌿+EcoTienda+HN" alt="EcoTienda HN Banner" width="100%">

# 🌿 EcoTienda HN

### Marketplace de Productos Ecológicos Hondureños

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-10.4-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)](https://chartjs.org)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-7.1-10B981?style=for-the-badge)](https://github.com/PHPMailer/PHPMailer)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

**E-commerce completo desarrollado como proyecto universitario en CEUTEC Honduras.**  
Conecta consumidores con productores ecológicos locales a través de una plataforma moderna, segura y escalable.

[🎬 Ver Demo en YouTube](#) · [📋 Reportar Bug](../../issues) · [🌱 Funcionalidades](#-funcionalidades)

</div>

---

## 📸 Capturas de Pantalla

| Página Principal | Tienda | Panel Admin |
|:---:|:---:|:---:|
| ![Home](https://placehold.co/380x220/064e3b/fff?text=Home) | ![Tienda](https://placehold.co/380x220/065f46/fff?text=Tienda) | ![Admin](https://placehold.co/380x220/0f172a/10b981?text=Admin) |
| **Carrito** | **Checkout** | **Mis Pedidos** |
| ![Carrito](https://placehold.co/380x220/064e3b/fff?text=Carrito) | ![Checkout](https://placehold.co/380x220/065f46/fff?text=Checkout) | ![Pedidos](https://placehold.co/380x220/0f172a/10b981?text=Pedidos) |

---

## ✅ Funcionalidades

### 🛒 Tienda / Cliente
- **Catálogo** con 15+ productos ecológicos hondureños reales
- **Búsqueda en tiempo real** con AJAX (sin recargar la página)
- **Filtros** por categoría, precio y disponibilidad
- **Paginación** configurable (8 productos por página)
- **Carrito dinámico** actualizado con AJAX/fetch()
- **Checkout** con 3 métodos de pago: Transferencia, PayPal, Tarjeta
- **Sistema de reseñas** con calificación de 1-5 estrellas
- **Favoritos** con botón de corazón (toggle instantáneo)
- **Mis Pedidos** con historial y estados en tiempo real
- **Perfil de usuario** editable (nombre, apellido, teléfono, contraseña)
- **Recuperación de contraseña** por email con token seguro (1 hora)
- **Correo de bienvenida** automático al registrarse

### 🔐 Autenticación y Seguridad
- Registro y login con validación completa
- Contraseñas hasheadas con `password_hash()` (bcrypt)
- Tokens CSRF en formularios sensibles
- Rate limiting en login (protección brute force)
- Variables de entorno con `vlucas/phpdotenv`
- Cabeceras de seguridad HTTP en `.htaccess`
- Queries con PDO y parámetros nombrados (sin SQL injection)
- Auditoría de acciones en tabla `auditoria`

### 🛠️ Panel Admin
- **Dashboard** con métricas reales en tiempo real
- **Gráfica de barras**: ventas de los últimos 6 meses (Chart.js)
- **Gráfica de dona**: distribución de pedidos por estado (Chart.js)
- **KPIs**: ingresos del mes, pedidos hoy, clientes activos, productos activos
- **Gestión de productos**: CRUD completo con subida de imágenes
- **Gestión de pedidos**: cambio de estado + email automático al cliente
- **Gestión de usuarios**: bloquear, activar, cambiar rol
- **Reportes** con exportación PDF (TCPDF)
- **Inventario**: movimientos de entrada y salida
- **Configuración** de datos de la tienda y redes sociales

### 💌 Email Automático (Gmail SMTP)
- Bienvenida al registrarse
- Confirmación de pedido con detalle de productos
- Notificación de cambio de estado del pedido
- Enlace de recuperación de contraseña (expira en 1 hora)

---

## 🛠️ Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.2 + PDO |
| Base de Datos | MySQL / MariaDB 10.4 |
| Frontend | Bootstrap 5.3 + Font Awesome 6 |
| Gráficas | Chart.js 4.4 |
| Email | PHPMailer 7.1 + Gmail SMTP |
| PDF | TCPDF |
| Entorno | vlucas/phpdotenv 5.6 |
| Servidor local | XAMPP (Apache 8080, MySQL 3307) |
| Control de versiones | Git + GitHub |

---

## ⚙️ Requisitos del Sistema

- **PHP** 8.0 o superior
- **MySQL** 5.7 / MariaDB 10.4 o superior
- **Apache** con `mod_rewrite` habilitado
- **Composer** (para dependencias PHP)
- **XAMPP** (recomendado para desarrollo local) o servidor Linux

---

## 🚀 Instalación Paso a Paso

### 1. Clonar el repositorio
```bash
git clone https://github.com/tuusuario/ecotienda-hn.git
cd ecotienda-hn
```

### 2. Instalar dependencias PHP
```bash
composer install
```

### 3. Configurar la base de datos
1. Abre **phpMyAdmin** → `http://localhost:8080/phpmyadmin`
2. Crea la base de datos `ecotienda_pro`
3. Importa el archivo principal:
   ```
   ecotienda_pro.sql
   ```
4. Importa los datos de demostración:
   ```
   ecotienda_seeders.sql
   ```

### 4. Configurar variables de entorno
```bash
cp .env.example .env
```
Edita `.env` con tus datos:
```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=ecotienda_pro
DB_USER=root
DB_PASS=

MAIL_USER=tu_correo@gmail.com
MAIL_PASS=tu_contrasena_de_aplicacion_google
MAIL_ENABLED=true
```

> **💡 Contraseña de app Gmail:**  
> `myaccount.google.com` → Seguridad → Verificación en 2 pasos → **Contraseñas de aplicación**

### 5. Configurar Apache
Copia la carpeta en `C:\xampp\htdocs\ecotienda-hn\` y asegúrate de que el `.htaccess` esté activo.

### 6. ¡Listo! Abrir en el navegador
```
http://localhost:8080/ecotienda-hn/
```

---

## 👤 Credenciales de Prueba

| Rol | Correo | Contraseña |
|-----|--------|-----------|
| **Admin** | `admin@ecotiendahn.com` | `admin123` |
| **Cliente** | `maria.lopez@gmail.com` | `Test1234!` |
| **Cliente** | `carlos.mejia@outlook.com` | `Test1234!` |

---

## 📁 Estructura del Proyecto

```
ecotienda-hn/
├── admin/                  # Panel de administración
│   ├── index.php           # Dashboard con métricas y Chart.js
│   ├── productos.php       # CRUD de productos
│   ├── pedidos.php         # Gestión de pedidos
│   ├── usuarios.php        # Gestión de usuarios
│   ├── categorias.php      # Gestión de categorías
│   ├── inventario.php      # Control de stock
│   ├── reportes.php        # Reportes con exportación PDF
│   └── configuracion.php   # Configuración de la tienda
├── api/                    # Endpoints JSON (AJAX)
│   ├── carrito.php         # GET/POST/PUT/DELETE carrito
│   ├── favoritos.php       # Toggle de favoritos
│   └── productos.php       # Búsqueda en tiempo real
├── assets/                 # Recursos estáticos
│   └── uploads/productos/  # Imágenes de productos
├── includes/               # Componentes reutilizables
│   ├── config.php          # Configuración global + .env
│   ├── database.php        # Conexión PDO singleton
│   ├── session.php         # Funciones de sesión y roles
│   ├── functions.php       # Utilidades, logError, redirect
│   ├── mailer.php          # Plantillas de email PHPMailer
│   ├── navbar.php          # Barra de navegación
│   └── footer.php          # Pie de página
├── logs/                   # Logs de errores (auto-generados)
├── vendor/                 # Dependencias Composer
├── .env                    # Variables de entorno (NO subir a Git)
├── .env.example            # Plantilla de variables de entorno
├── .gitignore              # Archivos ignorados por Git
├── .htaccess               # Configuración Apache + seguridad
├── composer.json           # Dependencias PHP
├── 404.php                 # Página de error 404
├── 500.php                 # Página de error 500
├── index.php               # Página principal
├── tienda.php              # Catálogo con AJAX y filtros
├── producto.php            # Detalle + reseñas
├── carrito.php             # Carrito de compras
├── checkout.php            # Proceso de pago
├── order_success.php       # Confirmación de pedido
├── login.php               # Inicio de sesión
├── register.php            # Registro de usuario
├── perfil.php              # Perfil editable
├── mis_pedidos.php         # Historial de pedidos
├── mis_favoritos.php       # Lista de favoritos
├── forgot_password.php     # Solicitar recuperación
├── reset_password.php      # Restablecer contraseña
├── sobre_nosotros.php      # Quiénes somos
├── faq.php                 # Preguntas frecuentes
└── contacto.php            # Formulario de contacto + mapa
```

---

## 🗄️ Diagrama de la Base de Datos

```
usuarios ──── pedidos ──── detalle_pedido ──── productos
   │              │                                │
   │           pagos                           categorias
   │              │                            marcas
   │         metodos_pago                      inventario
   │                                           producto_imagenes
   ├── favoritos ──── productos
   ├── carrito  ──── productos
   ├── resenas  ──── productos
   ├── direcciones
   └── auditoria
```

---

## 🌱 Impacto Ambiental

EcoTienda HN conecta a **productores hondureños** comprometidos con la sostenibilidad con consumidores que quieren hacer la diferencia. Cada compra apoya:

- 🌳 Agricultura orgánica certificada
- 🤝 Cooperativas de pequeños productores
- ♻️ Reducción de plásticos de un solo uso
- 🇭🇳 Economía local hondureña

---

## 🎓 Sobre el Proyecto

**Proyecto universitario — CEUTEC Honduras, Junio 2026**

Desarrollado como portafolio de desarrollo web full-stack, demostrando:
- Arquitectura MVC-like con PHP puro
- Seguridad: PDO, bcrypt, CSRF, rate limiting, .env
- APIs REST con JSON para operaciones asíncronas
- Integración de servicios externos (Gmail SMTP, Chart.js, Leaflet.js)
- Panel de administración completo con métricas reales

---

## 📄 Licencia

Distribuido bajo la Licencia MIT. Ver `LICENSE` para más información.

---

<div align="center">

Hecho con 💚 en Honduras

**EcoTienda HN** · CEUTEC 2026

</div>
