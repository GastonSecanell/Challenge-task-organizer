
# Task Organizer – Gestión de Tareas (Challenge)

Este proyecto corresponde a un **challenge fullstack** desarrollado con **Laravel 12 (backend) + Vue 3 (frontend)**.

La aplicación permite gestionar tareas mediante un **CRUD completo**, incluyendo prioridades, etiquetas y estado de cada tarea.  
Además se implementó un sistema de **autenticación y control de acceso por roles**, permitiendo distintos niveles de permisos dentro del sistema.

Todo el entorno se ejecuta mediante **Docker**, por lo que la aplicación puede levantarse con un único comando.

---

# Objetivo del Challenge

Desarrollar una aplicación para **gestionar tareas**, utilizando:

- Backend **Laravel 12**
- Frontend **Vue 3**
- Base de datos **MySQL**
- Docker composer para ejecutar todo el entorno
- API REST para comunicación backend/frontend

---

# Stack utilizado

## Backend
- Laravel 12
- PHP 8.2
- MySQL
- Eloquent ORM
- API REST

## Frontend
- Vue 3
- Composition API
- Pinia (estado global)
- Axios (consumo de API)

## Infraestructura
- Docker
- Docker Compose
- PHP-FPM
- MySQL
- Nginx
- phpMyAdmin

---

# Funcionalidades implementadas

## Autenticación

El sistema incluye autenticación de usuarios mediante login.

Flujo de autenticación:

Login → token → acceso a API protegida

El frontend requiere iniciar sesión para acceder al sistema.

---

# Roles de usuario

El sistema implementa **control de acceso por roles**.

### Administrador

Puede:

- gestionar tareas
- crear tareas
- editar tareas
- eliminar tareas
- cambiar estado de tareas
- ver módulo de usuarios
- gestionar usuarios

---

### Usuario Consulta

Tiene acceso limitado.

Puede:

- ver tareas
- ver prioridades
- ver etiquetas

No puede:

- crear tareas
- editar tareas
- eliminar tareas
- ver la vista de usuarios

El sistema funciona completamente en **modo lectura**.

---

# Control de acceso en frontend

El frontend protege tanto **rutas** como **componentes**.

Si el usuario no tiene permisos, no puede acceder a:

/usuarios

Incluso si intenta acceder manualmente por URL.

Además los botones de edición y eliminación se ocultan automáticamente según el rol del usuario.

---

# Gestión de tareas

El sistema permite:

- crear tareas
- editar tareas
- eliminar tareas
- ver una tarea específica
- listar todas las tareas

---

# Estados de tarea

Cada tarea puede tener uno de los siguientes estados:

pendiente  
en_progreso  
completada

---

# Prioridades

Cada tarea tiene una prioridad asociada.

BAJA  
MEDIA  
ALTA  

Relación:

Prioridad → Tareas

---

# Etiquetas

Las tareas pueden tener una o varias etiquetas.

DEV  
QA  
RRHH  

Relación:

Tarea ↔ Etiquetas

---

# Vista de usuarios

El sistema incluye un módulo de **gestión de usuarios**.

Permite:

- listar usuarios
- editar usuarios
- modificar roles

Este módulo **solo es visible para administradores**.

Los usuarios con rol **consulta** no pueden acceder a esta sección ni por interfaz ni por ruta directa.

---

# Búsqueda de usuarios

En la vista de usuarios se puede buscar utilizando distintos criterios.

Se puede filtrar por:

- nombre
- correo electrónico
- rol
- búsqueda por texto

Los resultados se actualizan dinámicamente en la tabla.

---

# Búsqueda y filtros de tareas

La vista de tareas incluye filtros para facilitar la gestión.

Se puede buscar por:

- texto (título o descripción)
- estado
- prioridad
- etiquetas
- fecha de vencimiento

Los filtros pueden combinarse para obtener resultados más específicos.

---

# Tema visual

El frontend incluye soporte para **modo claro y modo oscuro**.

El usuario puede cambiar entre:

Light mode  
Dark mode

El tema se aplica globalmente en la aplicación.

---

# Estructura del proyecto

backend/        → API Laravel  
frontend/       → aplicación Vue  
docker/         → configuración docker  
postman/        → colección API  

Servicios incluidos en Docker:

php  
mysql  
nginx  
frontend  
phpmyadmin  

---

# Cómo levantar el proyecto

## 1 - Clonar repositorio

git clone <repo>  
cd challenge-task-organizer

---

## 2 - Levantar entorno con Docker

docker compose up

Durante el arranque se ejecuta automáticamente:

- instalación de dependencias
- generación de APP_KEY
- migraciones
- seeders de datos iniciales

---

# Accesos

### Backend API

http://localhost:8000

---

### Frontend

http://localhost:5173

---

### phpMyAdmin

http://localhost:8081

---

# Datos de prueba

Se incluyen datos iniciales mediante seeders.

Usuario administrador:

email: admin@tareas.local.com  
password: administrador

Usuario consulta:

email: consult@tareas.local.com  
password: consulta

---

# API REST

Endpoints principales:

GET    /api/tareas  
GET    /api/tareas/{id}  
POST   /api/tareas  
PUT    /api/tareas/{id}  
DELETE /api/tareas/{id}  
PATCH  /api/tareas/{id}/estado  

---

# Colección Postman

Se incluye una colección Postman para probar la API.

Ubicación:

postman/Challenge_Task_Organizer_Postman.json

---

# Estado del proyecto

✔ Challenge finalizado  
✔ Dockerizado  
✔ Autenticación implementada  
✔ Control de acceso por roles  
✔ Frontend funcional  

---

# Autor

**Gaston Secanell**  
Full Stack Developer  

Email  
gastonsecanell@gmail.com  

LinkedIn  
https://www.linkedin.com/in/gaston-secanell-126bb4260
