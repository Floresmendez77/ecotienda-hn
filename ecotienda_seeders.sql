-- ============================================================
-- ECOTIENDA HN — Seeders / Datos de Demostración
-- Importar DESPUÉS del SQL principal (ecotienda_pro.sql)
-- ============================================================
USE ecotienda_pro;

-- Marcas ecológicas hondureñas
INSERT IGNORE INTO `marcas` (`id`, `nombre`, `descripcion`) VALUES
(1, 'EcoNatural HN',   'Productos naturales artesanales de Honduras'),
(2, 'TejiendoVerde',   'Textiles sostenibles hechos en Honduras'),
(3, 'COMSA Marcala',   'Cooperativa cafetalera de Marcala, La Paz'),
(4, 'EcoHogar HN',     'Soluciones ecológicas para el hogar hondureño'),
(5, 'BambuVida',       'Productos de bambú biodegradables'),
(6, 'AgroVerde HN',    'Insumos agrícolas orgánicos certificados'),
(7, 'NaturaSkin HN',   'Cuidado de piel con ingredientes hondureños');

-- Categorías completas
INSERT IGNORE INTO `categorias` (`id`, `nombre`, `descripcion`, `estado`) VALUES
(1, 'Hogar Eco',        'Productos ecológicos para el hogar',       'activo'),
(2, 'Cuidado Personal', 'Cosméticos y cuidado natural',             'activo'),
(3, 'Alimentación',     'Alimentos orgánicos y naturales',          'activo'),
(4, 'Jardín',           'Todo para tu jardín sostenible',           'activo'),
(5, 'Ropa Sostenible',  'Moda consciente y textiles reciclados',    'activo'),
(6, 'Bienestar',        'Salud natural y aromaterapia',             'activo');

-- 15 Productos ecológicos hondureños
INSERT IGNORE INTO `productos`
  (`id`,`categoria_id`,`marca_id`,`nombre`,`slug`,`descripcion_corta`,`descripcion_larga`,`precio`,`precio_oferta`,`stock`,`estado`) VALUES
(1,  2, 1, 'Jabón Artesanal de Coco y Aloe Vera',
     'jabon-artesanal-coco-aloe',
     'Jabón 100% natural con aceite de coco hondureño y aloe vera orgánico. Sin sulfatos ni parabenos.',
     'Elaborado artesanalmente en Tegucigalpa con aceite de coco prensado en frío y aloe vera cultivado sin pesticidas. Ideal para pieles sensibles. Peso neto: 120g. pH balanceado.',
     85.00, 70.00, 48, 'activo'),

(2,  1, 2, 'Bolsa Reutilizable 100% Algodón Orgánico',
     'bolsa-reutilizable-algodon',
     'Reemplaza hasta 1,000 bolsas plásticas. Tejida con algodón orgánico certificado en Honduras.',
     'Resistente y lavable. Capacidad de 10kg. Medidas: 38x42cm con asa de 30cm. Ideal para el mercado, supermercado o como bolsa de playa. Cada bolsa evita el uso de 1,000 bolsas plásticas al año.',
     65.00, NULL, 92, 'activo'),

(3,  3, 3, 'Café Orgánico de Altura Marcala 340g',
     'cafe-organico-marcala',
     'Café de especialidad SHG, cultivo a 1,500 msnm. Tostado medio, notas a chocolate y caramelo.',
     'Producido por la cooperativa COMSA en Marcala, La Paz, Honduras. Certificado orgánico por OCIA. Tostado en pequeños lotes para conservar los aceites esenciales. Perfecto para prensa francesa, pour-over o espresso.',
     145.00, 125.00, 38, 'activo'),

(4,  4, 4, 'Kit Compostaje Familiar Inicio Sostenible',
     'kit-compostaje-familiar',
     'Todo lo que necesitas para compostar en casa: compostero 30L, activador y guía paso a paso.',
     'El kit incluye: compostero de plástico reciclado 30L con ventilación, activador de compostaje orgánico (500g), termómetro de suelo, guante de jardín y guía ilustrada en español. Convierte tus residuos orgánicos en abono en 6-8 semanas.',
     290.00, NULL, 22, 'activo'),

(5,  2, 7, 'Shampoo Sólido de Romero y Menta 80g',
     'shampoo-solido-romero-menta',
     'Sin plástico, sin sulfatos. Dura 80 lavadas. Estimula el crecimiento capilar con aceites esenciales.',
     'Formulado con aceite esencial de romero hondureño y menta piperita. Ideal para cuero cabelludo graso. Un shampoo sólido equivale a 2-3 botellas de shampoo líquido convencional. Libre de SLS, SLES, parabenos y aceite de palma.',
     110.00, 95.00, 44, 'activo'),

(6,  2, 5, 'Cepillo de Dientes Bambú Pack x4',
     'cepillo-dientes-bambu-x4',
     'Mango 100% bambú biodegradable. Cerdas de nylon libre de BPA. Incluye 4 unidades en colores.',
     'El mango de bambú se degrada en 6 meses. Cerdas suaves ideales para higiene dental diaria. Cada paquete ahorra 4 botellas plásticas del océano. Viene en caja de cartón reciclado. Colores: verde, azul, coral y amarillo.',
     120.00, NULL, 67, 'activo'),

(7,  1, 4, 'Velas de Cera de Abejas Artesanales Set x3',
     'velas-cera-abejas-set-3',
     'Cera pura de abejas hondureñas. Aroma natural de miel y vainilla. Tiempo quema: 40h cada una.',
     'Elaboradas con cera virgen de colmenas en Olancho, Honduras. Sin parafina, sin colorantes artificiales. Mecha de algodón 100% natural. Tiempo de combustión: 40 horas por vela. Diámetro 7cm, altura 10cm. Set de 3 velas en caja kraft.',
     185.00, 160.00, 30, 'activo'),

(8,  3, 6, 'Miel de Abeja Pura de Olancho 500ml',
     'miel-abeja-pura-olancho',
     'Miel cruda sin procesar, certificada orgánica. Cosechada en las montañas de Olancho, Honduras.',
     'Miel artesanal sin pasteurizar que conserva todas sus propiedades naturales: enzimas, antioxidantes y polen. Producida por apicultores de la comunidad de Catacamas, Olancho. Presentación: frasco de vidrio 500ml reutilizable.',
     175.00, NULL, 28, 'activo'),

(9,  5, 2, 'Camiseta Unisex Algodón Reciclado',
     'camiseta-unisex-algodon-reciclado',
     'Confeccionada con 50% algodón orgánico y 50% PET reciclado. Tallas S, M, L, XL. Color natural.',
     'Cada camiseta recicla el equivalente a 5 botellas PET de 500ml. Confeccionada en Honduras por artesanas textiles de Intibucá. Tintura con plantas naturales: índigo, añil y cúrcuma. Lavado a máquina hasta 40°C. Certificación GOTS.',
     320.00, 280.00, 35, 'activo'),

(10, 6, 1, 'Aceite Esencial de Lavanda Hondureña 30ml',
     'aceite-esencial-lavanda-30ml',
     'Destilado al vapor de lavanda cultivada en Siguatepeque. Puro, sin diluir. Usos: aromaterapia y cosmética.',
     'Lavanda cultivada a 1,700 msnm en el municipio de Siguatepeque, Comayagua. Sin aditivos, sin dilución. Uso: difusor (5-8 gotas), baño relajante (10 gotas), masaje (diluido en aceite base). Frasco ámbar con gotero de vidrio.',
     225.00, NULL, 20, 'activo'),

(11, 4, 6, 'Fertilizante Orgánico Bokashi 2kg',
     'fertilizante-organico-bokashi-2kg',
     'Fermentado de EM (microorganismos efectivos) para hortalizas y jardines. Mejora la estructura del suelo.',
     'Elaborado con estiércol bovino fermentado, melaza orgánica y microorganismos benéficos. Aplicar 100g por m² cada 30 días. Apto para agricultura orgánica certificada. Bolsa de papel kraft reutilizable. Registrado en SAG Honduras.',
     95.00, NULL, 55, 'activo'),

(12, 1, 4, 'Filtro de Agua Cerámico Artesanal',
     'filtro-agua-ceramico-artesanal',
     'Reduce bacterias, sedimentos y cloro. Barro cocido hondureño. Capacidad: 20 litros. Sin electricidad.',
     'Fabricado con arcilla local de Comayagua y aserrín de pino. El proceso de filtración elimina el 99.8% de E. coli y coliformes. Caudal: 2-4 litros/hora. Incluye recipiente plástico de almacenamiento y llave de paso. Certificado por Unicef Honduras.',
     450.00, 399.00, 12, 'activo'),

(13, 2, 7, 'Protector Solar Natural FPS 30 60ml',
     'protector-solar-natural-fps30',
     'Base de óxido de zinc no nano. Sin químicos dañinos al arrecife. Apto para piel sensible y niños.',
     'Formulado con aceite de coco, manteca de karité y óxido de zinc no nano (FPS 30). Libre de oxibenzona, octinoxato y nanopartículas. Resistente al agua (40min). Compatible con arrecifes de coral. Ideal para Roatán, Tela y playas de Honduras.',
     195.00, NULL, 33, 'activo'),

(14, 3, 3, 'Cacao en Polvo Orgánico de Copán 200g',
     'cacao-polvo-organico-copan',
     'Cacao criollo hondureño de Copán, sin azúcar añadida. Rico en magnesio y antioxidantes.',
     'Cultivado por pequeños productores de la cooperativa APROCA en Santa Rita, Copán. Procesado a baja temperatura para conservar flavonoides. Uso: smoothies, repostería, bebidas calientes. Certificado orgánico. Sin gluten.',
     115.00, 99.00, 42, 'activo'),

(15, 6, 1, 'Kit Aromaterapia con Difusor Bambú',
     'kit-aromaterapia-difusor-bambu',
     'Difusor ultrasónico + 3 aceites esenciales hondureños (lavanda, eucalipto, naranja). Cobertura 25m².',
     'El difusor de bambú utiliza ultrasonidos para dispersar el aroma sin calor, conservando las propiedades terapéuticas. Incluye: difusor de bambú 200ml, aceite de lavanda 15ml, aceite de eucalipto 15ml, aceite de naranja dulce 15ml. Temporizador 1/3 horas. Luz LED nocturna.',
     380.00, 340.00, 18, 'activo');

-- 10 Usuarios de prueba (password = Test1234! para todos)
INSERT IGNORE INTO `usuarios` (`id`,`rol_id`,`nombre`,`apellido`,`correo`,`telefono`,`password`,`estado`) VALUES
(5,  2, 'María',    'López Martínez',  'maria.lopez@gmail.com',       '9841-2301', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(6,  2, 'Carlos',   'Mejía Rivera',    'carlos.mejia@outlook.com',    '9932-5512', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(7,  2, 'Diana',    'Mendoza Cruz',    'diana.mendoza@yahoo.com',     '9712-3388', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(8,  2, 'Josué',    'Rodríguez Paz',   'josue.rodriguez@gmail.com',   '9645-7741', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(9,  2, 'Fernanda', 'Aguilar Soto',    'fernanda.aguilar@gmail.com',  '9823-0045', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(10, 2, 'Roberto',  'Castellanos Lima','roberto.cast@gmail.com',      '9556-2219', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(11, 2, 'Valeria',  'Flores Hernández','valeria.flores@outlook.com',  '9734-8823', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(12, 2, 'Andrés',   'Euceda Núñez',    'andres.euceda@gmail.com',     '9891-1102', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(13, 2, 'Sofía',    'Murillo Padilla',  'sofia.murillo@gmail.com',    '9677-4456', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo'),
(14, 2, 'Miguel',   'Torres Zelaya',   'miguel.torres@yahoo.com',     '9445-3371', '$2y$10$8vUr8s2g8YfkEwQlRePU6OfPMBqrm5z3kI2.NkRuAqJj0PkS7yR7W', 'activo');

-- 32 Pedidos en distintos estados y fechas (últimos 6 meses)
INSERT IGNORE INTO `pedidos` (`id`,`usuario_id`,`subtotal`,`descuento`,`envio`,`total`,`estado`,`fecha`) VALUES
(1,  5,  215.00, 0,   150, 365.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 170 DAY)),
(2,  6,  580.00, 0,   150, 730.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 155 DAY)),
(3,  7,  290.00, 29,  150, 411.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 140 DAY)),
(4,  8,  175.00, 0,   150, 325.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 130 DAY)),
(5,  9,  450.00, 45,  150, 555.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 120 DAY)),
(6,  10, 120.00, 0,   150, 270.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 115 DAY)),
(7,  5,  380.00, 0,   150, 530.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 100 DAY)),
(8,  11, 640.00, 64,  150, 726.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 95 DAY)),
(9,  12, 225.00, 0,   150, 375.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 88 DAY)),
(10, 6,  490.00, 0,   150, 640.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 80 DAY)),
(11, 13, 320.00, 32,  150, 438.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 75 DAY)),
(12, 7,  185.00, 0,   150, 335.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 68 DAY)),
(13, 14, 760.00, 0,   150, 910.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 62 DAY)),
(14, 8,  145.00, 0,   150, 295.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 55 DAY)),
(15, 9,  415.00, 41,  150, 524.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 50 DAY)),
(16, 10, 680.00, 0,   150, 830.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 45 DAY)),
(17, 5,  230.00, 0,   150, 380.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 42 DAY)),
(18, 11, 395.00, 0,   150, 545.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 38 DAY)),
(19, 12, 115.00, 0,   150, 265.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 33 DAY)),
(20, 6,  570.00, 57,  150, 663.00,  'entregado',  DATE_SUB(NOW(), INTERVAL 28 DAY)),
(21, 13, 290.00, 0,   150, 440.00,  'procesando', DATE_SUB(NOW(), INTERVAL 22 DAY)),
(22, 7,  450.00, 0,   150, 600.00,  'procesando', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(23, 14, 185.00, 0,   150, 335.00,  'enviado',    DATE_SUB(NOW(), INTERVAL 14 DAY)),
(24, 8,  640.00, 64,  150, 726.00,  'enviado',    DATE_SUB(NOW(), INTERVAL 12 DAY)),
(25, 9,  320.00, 0,   150, 470.00,  'enviado',    DATE_SUB(NOW(), INTERVAL 9 DAY)),
(26, 10, 175.00, 0,   150, 325.00,  'procesando', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(27, 5,  495.00, 0,   150, 645.00,  'pendiente',  DATE_SUB(NOW(), INTERVAL 5 DAY)),
(28, 11, 230.00, 23,  150, 357.00,  'pendiente',  DATE_SUB(NOW(), INTERVAL 4 DAY)),
(29, 12, 380.00, 0,   150, 530.00,  'pendiente',  DATE_SUB(NOW(), INTERVAL 3 DAY)),
(30, 13, 560.00, 0,   150, 710.00,  'pendiente',  DATE_SUB(NOW(), INTERVAL 2 DAY)),
(31, 6,  145.00, 0,   150, 295.00,  'cancelado',  DATE_SUB(NOW(), INTERVAL 60 DAY)),
(32, 14, 290.00, 0,   150, 440.00,  'cancelado',  DATE_SUB(NOW(), INTERVAL 35 DAY));

-- Detalles de pedidos
INSERT IGNORE INTO `detalle_pedido` (`pedido_id`,`producto_id`,`cantidad`,`precio`,`subtotal`) VALUES
(1,  1, 1,  85.00,  85.00), (1,  2, 2,  65.00, 130.00),
(2,  3, 2, 145.00, 290.00), (2,  6, 1, 120.00, 120.00), (2, 5, 1, 110.00, 110.00),
(3,  4, 1, 290.00, 290.00),
(4,  8, 1, 175.00, 175.00),
(5,  9, 1, 320.00, 320.00), (5,  5, 1, 110.00, 110.00),
(6,  6, 1, 120.00, 120.00),
(7,  7, 1, 185.00, 185.00), (7,  1, 1,  85.00,  85.00), (7, 11, 1, 95.00, 95.00),
(8, 12, 1, 450.00, 450.00), (8,  2, 1,  65.00,  65.00), (8,  1, 1, 85.00, 85.00),
(9, 10, 1, 225.00, 225.00),
(10, 3, 1, 145.00, 145.00), (10, 14, 1, 115.00, 115.00), (10, 8, 1, 175.00, 175.00),
(11, 9, 1, 320.00, 320.00),
(12, 7, 1, 185.00, 185.00),
(13,15, 1, 380.00, 380.00), (13,12, 1, 450.00, 450.00),
(14, 3, 1, 145.00, 145.00),
(15, 5, 1, 110.00, 110.00), (15,13, 1, 195.00, 195.00), (15, 6, 1, 120.00, 120.00),
(16,12, 1, 450.00, 450.00), (16, 9, 1, 320.00, 320.00),
(17, 1, 1,  85.00,  85.00), (17, 8, 1, 175.00, 175.00),
(18,15, 1, 380.00, 380.00),
(19, 6, 1, 120.00, 120.00),
(20, 3, 2, 145.00, 290.00), (20,14, 1, 115.00, 115.00),
(21, 4, 1, 290.00, 290.00),
(22, 9, 1, 320.00, 320.00), (22, 5, 1, 110.00, 110.00),
(23, 7, 1, 185.00, 185.00),
(24,12, 1, 450.00, 450.00), (24, 2, 1,  65.00,  65.00),
(25, 9, 1, 320.00, 320.00),
(26, 8, 1, 175.00, 175.00),
(27,15, 1, 380.00, 380.00), (27, 1, 1,  85.00,  85.00),
(28, 2, 2,  65.00, 130.00), (28,11, 1,  95.00,  95.00),
(29, 7, 1, 185.00, 185.00), (29,13, 1, 195.00, 195.00),
(30, 3, 2, 145.00, 290.00), (30,10, 1, 225.00, 225.00),
(31, 6, 1, 120.00, 120.00), (31, 2, 1,  65.00,  65.00),
(32, 4, 1, 290.00, 290.00);

-- Favoritos de los usuarios
INSERT IGNORE INTO `favoritos` (`usuario_id`,`producto_id`) VALUES
(5,1),(5,3),(5,7),(6,2),(6,9),(7,4),(7,12),(8,5),(8,8),
(9,15),(9,3),(10,6),(11,1),(11,10),(12,13),(13,7),(14,3),(14,9);

-- Reseñas de productos
INSERT IGNORE INTO `resenas` (`producto_id`,`usuario_id`,`calificacion`,`comentario`) VALUES
(1, 5, 5, 'Excelente jabón, mi piel quedó súper suave y el olor es increíble. Lo recomiendo 100%.'),
(1, 6, 4, 'Muy buen producto, aunque tardó un poco en llegar. El empaque estaba perfecto.'),
(3, 7, 5, 'El mejor café que he probado en Honduras. Las notas a chocolate son increíbles.'),
(3, 8, 5, 'Ya voy por mi tercer pedido. No cambio este café por nada.'),
(6, 9, 4, 'Los cepillos de bambú son muy cómodos. El mango se siente natural y agradable.'),
(9, 10, 5, 'La camiseta tiene una calidad premium. El algodón orgánico se siente diferente.'),
(5, 11, 5, 'Me eliminó la caspa en 2 semanas. El olor a romero es relajante.'),
(12, 12, 5, 'El filtro funciona increíble. El agua sale cristalina y sin sabor a cloro.'),
(7, 13, 4, 'Las velas duran mucho más de lo esperado. El aroma natural de miel es delicado.'),
(8, 14, 5, 'Miel auténtica, sin procesar. Se nota la diferencia con las de supermercado.');
