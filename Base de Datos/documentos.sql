--Configuración
connect / as  sysdba;
create user docsadmin identified by a_password;
grant all privileges to docsadmin;

create profile unlimited_pwd_prof limit
  password_life_time unlimited;
alter user docsadmin profile unlimited_pwd_prof;

disconnect;
connect docsadmin/docsadmin;

drop table revisiones;
drop table documentos;
drop table tipos;
drop table usuarios;
drop table roles;
drop table procesos;
drop table departamentos;
drop table subdirecciones;
drop table estados;


drop sequence incrementa_subdireccion;
drop sequence incrementa_departamento;
drop sequence incrementa_proceso;
drop sequence incrementa_rol;
drop sequence incrementa_usuario;
drop sequence incrementa_tipo;
drop sequence incrementa_documento;
drop sequence incrementa_revision;

delete from subdirecciones;
delete from departamentos;
delete from procesos;
delete from usuarios;
delete from documentos;
delete from revisiones;

--Tablas
create table subdirecciones(
	id_subdireccion number(5) primary key,
	nombre varchar2(64) unique not null,
	estado number(1) not null
);

create table departamentos(
	id_depto number(5) primary key,
	nombre varchar2(64) unique not null,
	lider number(5),
	subdireccion number(5),
	estado number(1) not null
);

create table procesos(
	id_proceso number(5) primary key,
	nombre varchar2(128) unique not null,
	departamento number(5),
	estado number(1) not null
);

create table roles(
	id_rol number(5) primary key,
	rol varchar2(32) unique not null,
	estado number(1) not null
);

create table estados(
	id_estado number(5) primary key,
	estado varchar2(32) unique not null
);

create table usuarios(
	id_usuario number(5) primary key,
	nombre varchar2(64) not null,
	apellido varchar2(64) not null,
	puesto varchar2(64) not null,
	correo varchar2(64) unique not null,
	password varchar2(256) not null,
	departamento number(5),
	rol number(5),
	estado number(1) not null
);

create table tipos(
	id_tipo number(5) primary key,
	tipo varchar2(128) unique not null,
	estado number(1) not null
);

create table documentos(
	id_documento number(5) primary key,
	nombre varchar2(128) unique not null,
	tipo number(5),
	proceso number(5),
	codigo varchar2(64) unique not null,
	fecha_inicio date not null,
	fecha_fin  date,
	ubicacion varchar2(512) not null,
	observacion varchar2(512),
	estado number(1) not null
);

create  table revisiones(
	id_revision number(5) primary key,
	no_revision number(5) not null,
	documento number(5),
	responsable number(5),
	fecha_revision date not null,
	vigente number(1) not null,
	observacion varchar2(512),
	ruta varchar2(512) not null
);

--Secuencias para autoincrementar los id's de cada tabla
create sequence incrementa_estado increment by 1 minvalue 0 start with 0 nocache;
create sequence incrementa_subdireccion increment by 1 start with 1 nocache;
create sequence incrementa_departamento increment by 1 start with 1 nocache;
create sequence incrementa_proceso increment by 1 start with 1 nocache;
create sequence incrementa_rol increment by 1 start with 1 nocache;
create sequence incrementa_usuario increment by 1 start with 1 nocache;
create sequence incrementa_tipo increment by 1 start with 1 nocache;
create sequence incrementa_documento increment by 1 start with 1 nocache;
create sequence incrementa_revision increment by 1 start with 1 nocache;


ALTER TABLE subdirecciones add constraint fk_subdireccion_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE tipos add constraint fk_tipo_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE roles add constraint fk_rol_estado foreign key (estado) 
			references estados(id_estado);

ALTER TABLE departamentos add constraint fk_depto_subdireccion foreign key (subdireccion) 
			references subdirecciones(id_subdireccion);
			
ALTER TABLE departamentos add constraint fk_depto_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE procesos add constraint fk_proceso_depto foreign key (departamento) 
			references departamentos(id_depto);
			
ALTER TABLE procesos add constraint fk_proceso_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE documentos add constraint fk_documento_proceso foreign key (proceso) 
			references procesos(id_proceso);

ALTER TABLE documentos add constraint fk_documento_tipo foreign key (tipo) 
			references tipos(id_tipo);
			
ALTER TABLE documentos add constraint fk_documento_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE usuarios add constraint fk_usuario_estado foreign key (estado) 
			references estados(id_estado);
			
ALTER TABLE usuarios add constraint fk_usuario_rol foreign key (rol) 
			references roles(id_rol);
			
ALTER TABLE usuarios add constraint fk_usuario_departamento foreign key (departamento) 
			references departamentos(id_depto);
			
ALTER TABLE revisiones add constraint fk_revision_estado foreign key (vigente) 
			references estados(id_estado);
			
ALTER TABLE revisiones add constraint fk_revision_usuario foreign key (responsable) 
			references usuarios(id_usuario);
			
ALTER TABLE revisiones add constraint fk_revision_documento foreign key (documento) 
			references documentos(id_documento);

--Trigger que valida que el departamento y rol que se le asignan a un usuario estén activos (estado igual a 1)
--Tambien valida que al querer dar de baja a un usuario, éste no sea líder de departamento
CREATE OR REPLACE TRIGGER trigger_valida_usuario
	BEFORE INSERT OR UPDATE
	ON usuarios REFERENCING NEW AS n OLD AS o
	FOR EACH ROW
	DECLARE
		estado_depto number;
		estado_rol number;
		deptos number;
		users number;
		depto varchar2(64);
		pragma autonomous_transaction;
	BEGIN
		SELECT estado into estado_depto from departamentos where id_depto = :n.departamento;
		IF(estado_depto = 0)
			THEN
				RAISE_APPLICATION_ERROR(-20001, '_El departamento ingresado ya no está disponible_');
		END IF;
		SELECT estado into estado_rol from roles where id_rol = :n.rol;
		IF(estado_rol = 0)
			THEN
				RAISE_APPLICATION_ERROR(-20002, '_El rol ingresado ya no está disponible_');
		END IF;
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_depto) into deptos FROM departamentos where lider = :n.id_usuario;
				IF(deptos >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20003, '_No puedes dar de baja a un jefe de departamento_');
				END IF;
				IF(:o.rol = 1)
					THEN RAISE_APPLICATION_ERROR(-20016, '_No puedes dar de baja al Coordinador del SGC_');
				END IF;
		END IF;
		IF(:o.rol = 1 and :n.rol != 1)
			THEN
				SELECT COUNT(id_usuario) into users FROM usuarios WHERE  rol = 1 and estado !=0;
				IF(users <=0)
					THEN RAISE_APPLICATION_ERROR(-20017, '_Debe existir al menos un Coordinador del SGC_');
				END IF;
		END IF;
		--IF(:o.rol = 2 and :n.rol !=2)
			--THEN
				--SELECT COUNT(id_usuario) into users FROM usuarios WHERE  rol = 2 and estado !=0 and departamento = :n.departamento;
				--IF(users < 2)
					--THEN RAISE_APPLICATION_ERROR(-20020, '_No puedes cambiar el rol de un Jefe de Departamento_');
			--END IF;
		--END IF;
		IF(:n.rol = 1)
			THEN
				--SELECT COUNT(id_usuario) into users FROM usuarios WHERE  rol = 1 and estado = 1 and id_usuario != :n.id_usuario;
					--IF(users >=1)
						--THEN RAISE_APPLICATION_ERROR(-20018, '_No puede haber más de un Coordinador del SGC, y ya existe uno_');
					--END IF;
				SELECT nombre into depto FROM departamentos WHERE  id_depto = :n.departamento;
					IF(depto != 'Evaluación y Gestión de la Calidad')
						THEN RAISE_APPLICATION_ERROR(-20019, '_El Coordinador del SGC sólo puede pertenecer al departamento 
						de Evaluación y Gestión de la Calidad_');
					END IF;
		END IF;
	END;

--Trigger que valida que la subdirección y el lider que se le asignan a un departamento estén activos (estado igual a 1)
--También valida que no se de de baja un departamento que tenga usuarios o procesos asignados
CREATE OR REPLACE TRIGGER trigger_valida_departamento
	BEFORE INSERT OR UPDATE
	ON departamentos REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		estado_subdireccion number;
		estado_usuario number;
		numero_usuarios number;
		numero_procesos number;
	BEGIN
		SELECT estado into estado_subdireccion from subdirecciones where id_subdireccion = :n.subdireccion;
		IF(estado_subdireccion = 0)
			THEN
				RAISE_APPLICATION_ERROR(-20004, '_La subdirección ingresada ya no está disponible_');
		END IF;
		SELECT estado into estado_usuario from usuarios where id_usuario = :n.lider;
		IF(estado_usuario = 0)
			THEN
				RAISE_APPLICATION_ERROR(-20005, '_El lider de departamento ingresado ya no está disponible_');
		END IF;
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_usuario) INTO numero_usuarios FROM usuarios WHERE departamento = :n.id_depto;
				IF(numero_usuarios >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20006, '_No puedes dar de baja un departamento si tiene usuarios_');
				END IF;
				SELECT COUNT(id_proceso) INTO numero_procesos FROM procesos WHERE departamento = :n.id_depto;
				IF(numero_procesos >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20007, '_No puedes dar de baja un departamento si tiene procesos_');
				END IF;
		END IF;
	END;
	
--Trigger que valida que al intentar dar de baja una subdirección, ésta no tenga algún departamento asignado
CREATE OR REPLACE TRIGGER trigger_valida_sub
	BEFORE  UPDATE
	OF estado ON subdirecciones REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE 
		numero_deptos number;
	BEGIN
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_depto) INTO numero_deptos FROM departamentos WHERE subdireccion = :n.id_subdireccion;
				IF(numero_deptos >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20008, '_No puedes dar de baja una subdirección si tiene departamentos_');
				END IF;
		END IF;
	END;
	
--Trigger que valida que el departamento que se le asigna a un proceso esté activo (estado igual a 1)
--Tambien valida que al querer dar de baja a un proceso, éste no tenga algún documento asignado
CREATE OR REPLACE TRIGGER trigger_valida_proceso
	BEFORE INSERT OR UPDATE
	ON procesos REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		estado_depto number;
		numero_documentos number;
	BEGIN
		SELECT estado into estado_depto from departamentos where id_depto = :n.departamento;
		IF(estado_depto = 0)
			THEN
				RAISE_APPLICATION_ERROR(-20009, '_El departamento ingresado ya no está disponible_');
		END IF;
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_documento) into numero_documentos FROM documentos where proceso = :n.id_proceso;
				IF(numero_documentos >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20010, '_No puedes dar de baja un proceso con documentos_');
				END IF;
		END IF;
	END;
	
--Trigger que valida que al querer dar de baja un tipo de documento, éste no tenga algún documento asignado
CREATE OR REPLACE TRIGGER trigger_valida_tipo
	BEFORE UPDATE
	OF estado ON tipos REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		numero_documentos number;
	BEGIN
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_documento) into numero_documentos FROM documentos where tipo = :n.id_tipo;
				IF(numero_documentos >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20010, '_No puedes dar de baja un tipo de documento con documentos_');
				END IF;
		END IF;
	END;

--Trigger que valida que al querer dar de baja un rol, éste no tenga algún usuario asignado
CREATE OR REPLACE TRIGGER trigger_valida_rol
	BEFORE UPDATE
	OF estado ON roles REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		numero_usuarios number;
	BEGIN
		IF(:n.estado = 0)
			THEN
				SELECT COUNT(id_usuario) into numero_usuarios FROM usuarios where rol = :n.id_rol;
				IF(numero_usuarios >= 1)
					THEN
						RAISE_APPLICATION_ERROR(-20011, '_No puedes dar de baja un rol de usuario con usuarios_');
				END IF;
		END IF;
	END;	


--Trigger que valida que el proceso y el tipo de documento que se le asignan a un documento estén activos (estado igual a 1)
CREATE OR REPLACE TRIGGER trigger_valida_documento
	BEFORE INSERT OR UPDATE
	ON documentos REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		estado_proceso number;
		estado_tipo number;
	BEGIN
		SELECT estado into estado_proceso from procesos where id_proceso = :n.proceso;
		IF(estado_proceso = 0)
			THEN
				RAISE_APPLICATION_ERROR(-200012, '_El proceso ingresado ya no está disponible_');
		END IF;
		SELECT estado into estado_tipo from tipos where id_tipo = :n.tipo;
		IF(estado_tipo = 0)
			THEN
				RAISE_APPLICATION_ERROR(-200013, '_El tipo de documento ingresado ya no está disponible_');
		END IF;
	END;
	
--Trigger que valida que el documento que se le asigna a una revisión esté activo (estado igual a 1)
CREATE OR REPLACE TRIGGER trigger_valida_revision
	BEFORE INSERT OR UPDATE
	ON revisiones REFERENCING NEW AS n
	FOR EACH ROW
	DECLARE
		estado_documento number;
		estado_responsable number;
	BEGIN
		SELECT estado into estado_documento from documentos where id_documento = :n.documento;
		IF(estado_documento = 0)
			THEN
				RAISE_APPLICATION_ERROR(-200014, '_El documento ingresado ya no está disponible_');
		END IF;
		SELECT estado into estado_responsable from usuarios where id_usuario = :n.responsable;
		IF(estado_responsable = 0)
			THEN
				RAISE_APPLICATION_ERROR(-200015, '_El usuario responsable ya no está disponible_');
		END IF;
	END;
	
--FUNCIONES
CREATE OR REPLACE FUNCTION cuenta_revisiones(id_documento documentos.id_documento%TYPE) RETURN number
	AS
		num_revisiones number;
	BEGIN
		SELECT COUNT(id_revision) INTO num_revisiones FROM revisiones WHERE documento = id_documento;
		RETURN num_revisiones;
	END cuenta_revisiones;
	
insert into estados values(
	0,
	'Inactivo'
);
insert into estados values(
	(SELECT NVL(MAX(id_estado), 0) + 1 FROM estados),
	'Activo'
);
insert into estados values(
	(SELECT NVL(MAX(id_estado), 0) + 1 FROM estados),
	'Esperando Revisión'
);

insert into subdirecciones values(
	(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
	'Planeación y Evaluación de la Educación Virtual',
	1
);
insert into subdirecciones values(
	(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
	'Diseño y Desarrollo',
	1
);
insert into subdirecciones values(
	(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
	'Integración de Tecnologías',
	1
);
insert into subdirecciones values(
	(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
	'Gestión',
	1
);

insert into subdirecciones values(
	(SELECT NVL(MAX(id_subdireccion), 0) + 1 FROM subdirecciones),
	'Servicios Administrativos',
	1
);

ALTER TRIGGER trigger_valida_departamento disable;

insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Planeación y Promoción',
	null,
	1,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Evaluación y Gestión de la Calidad',
	null,
	1,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Coordinación de Programas',
	null,
	2,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Formación en Ambientes Virtuales',
	null,
	2,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Investigación e Innovación',
	null,
	2,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Televisión Educativa',
	null,
	3,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Desarrollo Tecnológico y Soporte Técnico',
	null,
	3,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Producción de Recursos Educativos',
	null,
	3,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Medios de Cominicación Educativa',
	null,
	3,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Atención a Usuarios',
	null,
	4,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Logística',
	null,
	4,
	1
);
insert into departamentos values(
	(SELECT NVL(MAX(id_depto), 0) + 1 FROM departamentos),
	'Servicios Administrativos',
	null,
	5,
	1
);

insert into procesos values(
	(SELECT NVL(MAX(id_proceso), 0) + 1 FROM procesos),
	'Revisión por la Dirección',
	2, 
	1
);

insert into roles values(
	(SELECT NVL(MAX(id_rol), 0) + 1 FROM roles),
	'Coordinación del SGC',
	1
);
insert into roles values(
	(SELECT NVL(MAX(id_rol), 0) + 1 FROM roles),
	'Líder de Proceso',
	1
);
insert into roles values(
	(SELECT NVL(MAX(id_rol), 0) + 1 FROM roles),
	'Consulta de Departamento',
	1
);


insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Oswaldo Alexis',
	'Solache Palacios',
	'Coordinación SGC',
	'osolachep@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	--'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRNMkpVTVd0aldpODFNMDF6WjBoMVdRJGhPMm1qUnJmcmNyMHVSSm1xQzZJSU9pZlU2aHphRExFUzNGT0Y3bDVtUTA=',
	2,
	1,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Libna Elizabeth',
	'Oviedo Castillo',
	'Jefe de Departamento',
	'loviedo@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	2,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'DEGC',
	'UPEV',
	'Consulta de Departamento',
	'degc.upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	2,
	3,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Salvador',
	'Castro Gomez',
	'Jefe de Departamento',
	'scastrog@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	7,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Sporte',
	'UPEV',
	'Consulta de Departamento',
	'soporteupev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	7,
	3,
	2
);
-----------------------------------
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Jaasiel',
	'Garibay Andonaegui',
	'Jefe de Departamento',
	'dl_upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	11,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Israel',
	'Jimenez Nogueda',
	'Jefe de Departamento',
	'dpre_upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	8,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Jose Luis',
	'Hernandez Valencia',
	'Jefe de Departamento',
	'dmce_upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	9,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Irene',
	'Ramirez Cardenas',
	'Jefe de Departamento',
	'dte_upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	6,
	2,
	2
);
insert into usuarios values(
	(SELECT NVL(MAX(id_usuario), 0) + 1 FROM usuarios),
	'Irene',
	'Ramirez Cardenas',
	'Jefe de Departamento',
	'dte_upev@ipn.mx',
	'JGFyZ29uMmkkdj0xOSRtPTEwMjQsdD0yLHA9MiRXVUZaVjJORFZ6UjVPV05LUVdwQlNBJDUyelh2VVpLanFheWFjc0pGcTkyMGhxNjhDQ2xUdXRkNlEvWmpqOGRzblU=',
	6,
	2,
	2
);

insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Plan de Calidad',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Procedimiento Operativo',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Procedimiento del Sistema de Gestión de Calidad',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Procedimiento de Apoyo',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Formato',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Manual',
	1
);
insert into tipos values(
	(SELECT NVL(MAX(id_tipo), 0) + 1 FROM tipos),
	'Otro',
	1
);


insert into documentos values(
	(SELECT NVL(MAX(id_documento), 0) + 1 FROM documentos),
	'Atención a Solicitudes de Información y Asistencia de Aspirantes y Comunidad Polivirtual',
	5,
	1,
	'COD-PRUEB-01',
	SYSDATE,
	null,
	'Servidor de prueba en C:/archivos/formato.pdf',
	null,
	2
);

insert into documentos values(
	(SELECT NVL(MAX(id_documento), 0) + 1 FROM documentos),
	'Evaluación de la Auditoria',
	5,
	1,
	'COD-PRUEB-OTRO',
	SYSDATE,
	null,
	'Servidor de prueba en C:/archivos/formato.pdf',
	null,
	2
);

insert into documentos values(
	(SELECT NVL(MAX(id_documento), 0) + 1 FROM documentos),
	'Instructivo para el registro de control diario de atención a solicitudes de información',
	1,
	2,
	'DA/IRCD/01',
	SYSDATE,
	null,
	'Servidor de prueba en C:/archivos/formato.pdf',
	null,
	2
);

insert into revisiones values(
	(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
	5,
	1,
	SYSDATE,
	2,
	null,
	'92a8633b21b643e92f1bb8ab4b8d3212.pdf'
);

insert into revisiones values(
	(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
	6,
	1,
	SYSDATE,
	2,
	null,
	'92a8633b21b643e92f1bb8ab4b8d3212.pdf'
);

insert into revisiones values(
	(SELECT NVL(MAX(id_revision), 0) + 1 FROM revisiones),
	7,
	1,
	SYSDATE,
	2,
	null,
	'92a8633b21b643e92f1bb8ab4b8d3212.pdf'
);

ALTER TRIGGER trigger_valida_departamento enable;


--Vistas:
CREATE OR REPLACE VIEW v_usuarios_activos
	AS
	SELECT a.id_usuario,
		a.nombre,
		a.apellido,
		a.puesto,
		a.correo,
		a.password,
		a.departamento as id_departamento,
		b.nombre as departamento,
		c.nombre as subdireccion,
		a.rol as id_rol,
		d.rol,
		a.estado as id_estado,
		e.estado
		FROM usuarios a
			LEFT JOIN departamentos b ON a.departamento = b.id_depto
			LEFT JOIN subdirecciones c ON b.subdireccion = c.id_subdireccion
			LEFT JOIN roles d ON  d.id_rol = a.rol
			LEFT JOIN estados e ON  e.id_estado = a.estado
		WHERE a.estado != 0
			AND b.estado !=0
			AND c.estado !=0
			AND d.estado !=0;
		

CREATE OR REPLACE VIEW v_usuarios
	AS
	SELECT a.id_usuario,
		a.nombre,
		a.apellido,
		a.puesto,
		a.correo,
		a.password,
		a.departamento as id_departamento,
		b.nombre as departamento,
		c.nombre as subdireccion,
		a.rol as id_rol,
		d.rol,
		a.estado as id_estado,
		e.estado
		FROM usuarios a
			LEFT JOIN departamentos b ON a.departamento = b.id_depto
			LEFT JOIN subdirecciones c ON b.subdireccion = c.id_subdireccion
			LEFT JOIN roles d ON  d.id_rol = a.rol
			LEFT JOIN estados e ON  e.id_estado = a.estado;



CREATE OR REPLACE VIEW v_tipos_activos
	AS
	SELECT a.id_tipo,
		a.tipo,
		a.estado as id_estado,
		b.estado
		FROM tipos a
			LEFT JOIN estados b ON  b.id_estado = a.estado
		WHERE a.estado != 0;
		

CREATE OR REPLACE VIEW v_tipos
	AS
	SELECT a.id_tipo,
		a.tipo,
		a.estado as id_estado,
		b.estado
		FROM tipos a
			LEFT JOIN estados b ON  b.id_estado = a.estado;


CREATE OR REPLACE VIEW v_subdirecciones_activas
	AS
	SELECT a.id_subdireccion,
		a.nombre,
		a.estado as id_estado,
		b.estado
		FROM subdirecciones a
			LEFT JOIN estados b ON  b.id_estado = a.estado
		WHERE a.estado != 0;
	
CREATE OR REPLACE VIEW v_subdirecciones
	AS
	SELECT a.id_subdireccion,
		a.nombre,
		a.estado as id_estado,
		b.estado
		FROM subdirecciones a
			LEFT JOIN estados b ON  b.id_estado = a.estado;

CREATE OR REPLACE VIEW v_procesos_activos
	AS
	SELECT a.id_proceso,
		a.nombre,
		a.departamento as id_departamento,
		b.nombre as departamento,
		a.estado as id_estado,
		c.estado
		FROM procesos a
			LEFT JOIN departamentos b ON  b.id_depto = a.departamento
			LEFT JOIN estados c ON  c.id_estado = a.estado
		WHERE a.estado != 0
			AND b.estado !=0;		
		
CREATE OR REPLACE VIEW v_procesos
	AS
	SELECT a.id_proceso,
		a.nombre,
		a.departamento as id_departamento,
		b.nombre as departamento,
		a.estado as id_estado,
		c.estado
		FROM procesos a
			LEFT JOIN departamentos b ON  b.id_depto = a.departamento
			LEFT JOIN estados c ON  c.id_estado = a.estado;
		
		
CREATE OR REPLACE VIEW v_departamentos_activos
	AS
	SELECT a.id_depto,
		a.nombre,
		a.lider as id_lider,
		b.nombre || ' ' || b.apellido as lider,
		a.subdireccion as id_subdireccion,
		c.nombre as subdireccion,
		a.estado as id_estado,
		d.estado
		FROM departamentos a
			LEFT JOIN usuarios b ON  b.id_usuario = a.lider
			LEFT JOIN subdirecciones c ON  c.id_subdireccion = a.subdireccion
			LEFT JOIN estados d ON  d.id_estado = a.estado
		WHERE a.estado != 0
			AND b.estado != 0
			AND c.estado != 0;
		
		
CREATE OR REPLACE VIEW v_departamentos
	AS
	SELECT a.id_depto,
		a.nombre,
		a.lider as id_lider,
		b.nombre || ' ' || b.apellido as lider,
		a.subdireccion as id_subdireccion,
		c.nombre as subdireccion,
		a.estado as id_estado,
		d.estado
		FROM departamentos a
			LEFT JOIN usuarios b ON  b.id_usuario = a.lider
			LEFT JOIN subdirecciones c ON  c.id_subdireccion = a.subdireccion
			LEFT JOIN estados d ON  d.id_estado = a.estado;
		
		
CREATE OR REPLACE VIEW v_roles_activos
	AS
	SELECT a.id_rol,
		a.rol,
		a.estado as id_estado,
		b.estado
		FROM roles a
			LEFT JOIN estados b ON  b.id_estado = a.estado
		WHERE a.estado != 0;
	
CREATE OR REPLACE VIEW v_roles
	AS
	SELECT a.id_rol,
		a.rol,
		a.estado as id_estado,
		b.estado
		FROM roles a
			LEFT JOIN estados b ON  b.id_estado = a.estado;
			
		
CREATE OR REPLACE VIEW v_documentos_activos
	AS
	SELECT a.id_documento,
		a.nombre,
		a.tipo as id_tipo,
		b.tipo,
		a.proceso as id_proceso,
		c.nombre as proceso,
		e.nombre as departamento,
		e.id_depto,
		a.codigo,
		--a.fecha_inicio,
		f.fecha_revision as fecha_inicio,
		a.fecha_fin,
		cuenta_revisiones(a.id_documento) as num_revisiones,
		f.ruta,
		a.observacion,
		f.id_revision,
		f.no_revision,
		a.ubicacion,
		a.estado
		FROM documentos a
			LEFT JOIN tipos b ON  b.id_tipo = a.tipo
			LEFT JOIN procesos c ON  c.id_proceso = a.proceso
			LEFT JOIN departamentos e ON  e.id_depto = c.departamento
			LEFT JOIN revisiones f ON  f.documento = a.id_documento
			LEFT JOIN estados g ON  g.id_estado = a.estado
		WHERE a.estado = 1
			AND b.estado != 0
			AND c.estado != 0
			AND e.estado != 0
			AND f.vigente = 1;
			
			
CREATE OR REPLACE VIEW v_documentos
	AS
	SELECT a.id_documento,
		a.nombre,
		a.tipo as id_tipo,
		b.tipo,
		a.proceso as id_proceso,
		c.nombre as proceso,
		e.nombre as departamento,
		e.id_depto,
		a.codigo,
		a.fecha_inicio,
		a.fecha_fin,
		cuenta_revisiones(a.id_documento) as num_revisiones,
		f.ruta,
		a.observacion,
		a.ubicacion,
		a.estado
		FROM documentos a
			LEFT JOIN tipos b ON  b.id_tipo = a.tipo
			LEFT JOIN procesos c ON  c.id_proceso = a.proceso
			LEFT JOIN departamentos e ON  e.id_depto = c.departamento
			LEFT JOIN revisiones f ON  f.documento = a.id_documento
			LEFT JOIN estados g ON  g.id_estado = a.estado;
			
CREATE OR REPLACE VIEW v_revisiones_pendientes
	AS
	SELECT a.id_documento,
		a.nombre,
		a.tipo as id_tipo,
		b.tipo,
		a.proceso as id_proceso,
		c.nombre as proceso,
		e.nombre as departamento,
		e.id_depto,
		a.codigo,
		a.fecha_inicio,
		a.fecha_fin,
		f.fecha_revision,
		f.ruta,
		f.observacion,
		f.id_revision,
		f.no_revision,
		f.vigente as estado
		FROM documentos a
			LEFT JOIN tipos b ON  b.id_tipo = a.tipo
			LEFT JOIN procesos c ON  c.id_proceso = a.proceso
			LEFT JOIN departamentos e ON  e.id_depto = c.departamento
			LEFT JOIN revisiones f ON  f.documento = a.id_documento
			LEFT JOIN estados g ON  g.id_estado = a.estado
		WHERE a.estado = 1
			AND b.estado != 0
			AND c.estado !=0
			AND e.estado != 0
			AND f.vigente = 2;

CREATE OR REPLACE VIEW v_revisiones_vigentes
	AS
	SELECT a.id_documento,
		a.nombre,
		a.tipo as id_tipo,
		b.tipo,
		a.proceso as id_proceso,
		c.nombre as proceso,
		e.nombre as departamento,
		e.id_depto,
		a.codigo,
		a.fecha_inicio,
		a.fecha_fin,
		f.fecha_revision,
		f.ruta,
		f.observacion,
		f.id_revision,
		f.no_revision,
		f.vigente as estado
		FROM documentos a
			LEFT JOIN tipos b ON  b.id_tipo = a.tipo
			LEFT JOIN procesos c ON  c.id_proceso = a.proceso
			LEFT JOIN departamentos e ON  e.id_depto = c.departamento
			LEFT JOIN revisiones f ON  f.documento = a.id_documento
			LEFT JOIN estados g ON  g.id_estado = a.estado
		WHERE a.estado = 1
			AND b.estado != 0
			AND c.estado !=0
			AND e.estado != 0
			AND f.vigente = 1;
			

revoke all privileges from docsadmin;

GRANT CREATE SESSION, UNLIMITED TABLESPACE TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.subdirecciones TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.departamentos TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.roles TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.usuarios TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.tipos TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.estados TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.procesos TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.documentos TO docsadmin;
GRANT SELECT, INSERT, UPDATE, DELETE ON docsadmin.revisiones TO docsadmin;