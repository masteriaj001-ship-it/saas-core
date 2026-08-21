# QA Checklist — ProyectDashboard

## Pre-requisitos
- [ ] Abrir `https://saascore-production.up.railway.app` en Chrome/Edge
- [ ] Login: `admin@demo.com` / `password`
- [ ] Seleccionar tenant `The Garage`
- [ ] Hard refresh (Ctrl+Shift+R) para limpiar caché

---

## 1. Autenticación

| # | Paso | Esperado | OK |
|---|---|---|---|
| 1.1 | Abrir `/login` | Formulario en español: "Acceso", "Correo electrónico", "Contraseña" | |
| 1.2 | Login con credenciales válidas | Redirige al dashboard | |
| 1.3 | Login con credenciales inválidas | Mensaje de error en español | |
| 1.4 | Cerrar sesión | Redirige a `/login` | |
| 1.5 | Acceder sin login | Redirige a `/login` | |

## 2. Sidebar / Navegación

| # | Paso | Esperado | OK |
|---|---|---|---|
| 2.1 | Sidebar visible en desktop | Se muestra con menú colapsado | |
| 2.2 | Click en icono hamburger | Sidebar se expande con labels | |
| 2.3 | Sidebar colapsable | Se colapsa al seleccionar módulo | |
| 2.4 | Todos los módulos visibles | Gestión, Inventario, Configuración | |
| 2.5 | Nombres en español | "Órdenes de Trabajo", "Repuestos/Insumos", etc. | |

## 3. POS (Punto de Venta)

| # | Paso | Esperado | OK |
|---|---|---|---|
| 3.1 | Abrir POS | Página full-screen, sin sidebar | |
| 3.2 | Ver catálogo | Muestra items con stock + servicios | |
| 3.3 | Buscar por nombre | Filtra resultados correctamente | |
| 3.4 | Buscar por SKU | Encuentra el item exacto | |
| 3.5 | Filtro "Todos" | Muestra todos los items | |
| 3.6 | Filtro "Productos" | Solo muestra productos | |
| 3.7 | Filtro "Servicios" | Muestra servicios (incluso sin stock) | |
| 3.8 | Filtro "Repuestos" | Solo muestra repuestos | |
| 3.9 | Agregar item al carrito | Aparece en ticket derecho | |
| 3.10 | Agregar mismo item 2 veces | Incrementa cantidad, no duplica línea | |
| 3.11 | Cambiar cantidad (+) | Subtotal se recalcula | |
| 3.12 | Cambiar cantidad (-) | Subtotal se recalcula | |
| 3.13 | Quitar item del carrito | Se elimina, carrito se actualiza | |
| 3.14 | Carrito vacío total | Botón cobrar deshabilitado | |
| 3.15 | Subtotal correcto | Suma de todos los subtotales | |
| 3.16 | Click "Cobrar (F2/F4)" | Abre modal de pago | |
| 3.17 | Seleccionar Efectivo | Campo de monto aparece | |
| 3.18 | Ingresar monto exacto | Vuelto = $0 | |
| 3.19 | Ingresar monto mayor | Vuelto se calcula correctamente | |
| 3.20 | Ingresar monto menor | Mensaje de error | |
| 3.21 | Seleccionar Tarjeta | No pide monto | |
| 3.22 | Confirmar pago | Se crea factura, se muestra ticket | |
| 3.23 | Cerrar ticket | Vuelve al catálogo limpio | |
| 3.24 | Historial (F5) | Muestra ventas recientes | |

## 4. Órdenes de Trabajo

| # | Paso | Esperado | OK |
|---|---|---|---|
| 4.1 | Ver lista de WO | Muestra todas las órdenes del tenant | |
| 4.2 | Click "Nueva Orden" | Abre wizard de 3 pasos | |
| 4.3 | **Paso 1 - Recepción** | | |
| 4.3a | Seleccionar cliente existente | Busca por nombre/doc/teléfono | |
| 4.3b | Crear cliente inline | Crea contacto type=client | |
| 4.3c | Seleccionar vehículo | Busca por placa/marca/modelo | |
| 4.3d | Crear vehículo inline | Crea asset type=vehicle | |
| 4.4 | **Paso 2 - Diagnóstico** | | |
| 4.4a | Agregar repuesto (tipo Repuesto) | Select de items aparece | |
| 4.4b | Buscar repuesto | Filtra por nombre/SKU | |
| 4.4c | Precio se auto-rellena | Toma precio del item | |
| 4.4d | Agregar servicio (tipo Servicio) | Select de catálogo aparece | |
| 4.4e | Buscar servicio | Filtra por nombre en ServiceCatalog | |
| 4.4f | Precio se auto-rellena | Toma base_price del catálogo | |
| 4.4g | Agregar mano de obra (tipo Mano de Obra) | Campo de descripción aparece | |
| 4.4h | Subtotales calculados | quantity * unit_price | |
| 4.5 | **Paso 3 - Cierre** | | |
| 4.6 | Guardar WO | Redirige a lista, muestra notificación | |
| 4.7 | Editar WO existente | Carga datos correctamente | |
| 4.8 | Cambiar estado | Draft → In Progress → Completed | |
| 4.9 | Eliminar WO | Pide confirmación, elimina | |

## 5. Catálogo de Servicios

| # | Paso | Esperado | OK |
|---|---|---|---|
| 5.1 | Ver lista | Muestra servicios del tenant | |
| 5.2 | Crear servicio | Nombre, precio, tiempo estimado | |
| 5.3 | Editar servicio | Modifica precio | |
| 5.4 | Activar/Desactivar | Toggle funciona | |
| 5.5 | Eliminar servicio | Pide confirmación | |

## 6. Repuestos/Insumos (Items)

| # | Paso | Esperado | OK |
|---|---|---|---|
| 6.1 | Ver lista | Muestra items del tenant | |
| 6.2 | Crear repuesto | SKU, nombre, precio, stock | |
| 6.3 | Crear servicio como item | item_type=service, stock=0 | |
| 6.4 | Editar item | Modifica campos | |
| 6.5 | SKU único por tenant | Error al duplicar SKU | |

## 7. Contactos

| # | Paso | Esperado | OK |
|---|---|---|---|
| 7.1 | Ver lista | Muestra contactos del tenant | |
| 7.2 | Crear cliente | name, phone, contact_type=client | |
| 7.3 | Crear proveedor | contact_type=supplier | |
| 7.4 | Editar contacto | Modifica campos | |
| 7.5 | Buscar contacto | Filtra por nombre/teléfono | |

## 8. Activos (Vehículos)

| # | Paso | Esperado | OK |
|---|---|---|---|
| 8.1 | Ver lista | Muestra activos del tenant | |
| 8.2 | Crear vehículo | nombre, placa, marca, modelo | |
| 8.3 | Editar vehículo | Modifica campos | |
| 8.4 | Vincular a WO | Aparece en selección de WO | |

## 9. UI/UX

| # | Paso | Esperado | OK |
|---|---|---|---|
| 9.1 | Dark mode | Tema oscuro forzado | |
| 9.2 | Sidebar colapsable | Funciona en desktop | |
| 9.3 | Contenido full-width | Se expande al colapsar sidebar | |
| 9.4 | Responsive móvil | Sidebar colapsado, contenido visible | |
| 9.5 | Traducciones | Botones/mensajes en español | |
| 9.6 | Contraseña revealable | Ojito funciona en desktop | |
| 9.7 | Sin errores en consola | No hay errores JS | |

## 10. Edge Cases

| # | Paso | Esperado | OK |
|---|---|---|---|
| 10.1 | POS sin items | Mensaje de carrito vacío | |
| 10.2 | WO sin cliente | Validación de required | |
| 10.3 | WO sin vehículo | Validación de required | |
| 10.4 | Item con stock 0 en WO | Permite agregar repuesto | |
| 10.5 | Servicio sin precio | Permite guardar con precio 0 | |
| 10.6 | Cantidad decimal en repuesto | Acepta decimales | |
| 10.7 | Precio negativo | No debería permitirse | |
| 10.8 | Búsqueda vacía | Muestra todos los items | |
| 10.9 | Carrito con 10+ items | Scroll funciona | |
| 10.10 | Concurrente: 2 pestañas mismo tenant | No hay conflictos | |

---

## Bugs Encontrados

| # | Severidad | Descripción | Pantalla | Estado |
|---|---|---|---|---|
| | | | | |

## Oportunidades de Mejora

| # | Prioridad | Descripción | Archivo |
|---|---|---|---|
| | | | |
