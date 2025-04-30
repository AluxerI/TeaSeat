--
-- PostgreSQL database dump
--

-- Dumped from database version 16.6
-- Dumped by pg_dump version 16.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: addresclient; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.addresclient (
    id integer NOT NULL,
    arealife character varying(20) NOT NULL,
    cityliie character varying(20) NOT NULL,
    street character varying NOT NULL,
    numberhouse integer NOT NULL,
    numberflat integer
);


ALTER TABLE public.addresclient OWNER TO postgres;

--
-- Name: addresclent_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.addresclent_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.addresclent_id_seq OWNER TO postgres;

--
-- Name: addresclent_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.addresclent_id_seq OWNED BY public.addresclient.id;


--
-- Name: addreswarehouse; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.addreswarehouse (
    idwarehouse integer NOT NULL,
    area character varying(20) NOT NULL,
    city character varying(20) NOT NULL,
    street character varying(20) NOT NULL,
    numberhouse character varying(20) NOT NULL,
    numberflat character varying(20)
);


ALTER TABLE public.addreswarehouse OWNER TO postgres;

--
-- Name: associationproduct; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.associationproduct (
    idnameproduct integer NOT NULL,
    idassociation integer NOT NULL
);


ALTER TABLE public.associationproduct OWNER TO postgres;

--
-- Name: associations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.associations (
    id integer NOT NULL,
    imageassociation character varying
);


ALTER TABLE public.associations OWNER TO postgres;

--
-- Name: associations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.associations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.associations_id_seq OWNER TO postgres;

--
-- Name: associations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.associations_id_seq OWNED BY public.associations.id;


--
-- Name: subcategory; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subcategory (
    id integer NOT NULL,
    namecategoryi character varying(40),
    idtimediscount integer
);


ALTER TABLE public.subcategory OWNER TO postgres;

--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.categories_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categories_id_seq OWNER TO postgres;

--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.subcategory.id;


--
-- Name: category; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.category (
    id integer NOT NULL,
    namecategory character varying(63)
);


ALTER TABLE public.category OWNER TO postgres;

--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    datereg date NOT NULL
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: client_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_id_seq OWNER TO postgres;

--
-- Name: client_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_id_seq OWNED BY public.users.id;


--
-- Name: clientinfo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.clientinfo (
    clientid integer NOT NULL,
    addresid integer,
    numbertelephone character(12) NOT NULL,
    name character varying(31),
    surname character varying(31),
    patronymic character varying(31)
);


ALTER TABLE public.clientinfo OWNER TO postgres;

--
-- Name: discountclient; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.discountclient (
    idclient integer NOT NULL,
    iddiscount integer NOT NULL,
    usecount integer
);


ALTER TABLE public.discountclient OWNER TO postgres;

--
-- Name: listsubcategories; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.listsubcategories (
    idproduct integer NOT NULL,
    idcategory integer NOT NULL
);


ALTER TABLE public.listsubcategories OWNER TO postgres;

--
-- Name: managerwarehouse; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.managerwarehouse (
    idmanager integer NOT NULL,
    numbwarehouse integer NOT NULL
);


ALTER TABLE public.managerwarehouse OWNER TO postgres;

--
-- Name: markclient; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.markclient (
    idclient integer NOT NULL,
    idproduct integer NOT NULL,
    marks integer,
    komment text,
    CONSTRAINT markclient_marks_check CHECK (((marks > 0) AND (marks < 6)))
);


ALTER TABLE public.markclient OWNER TO postgres;

--
-- Name: nameproduct; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nameproduct (
    id integer NOT NULL,
    nameitem character varying(50) NOT NULL,
    description text,
    imageitem character varying(255)
);


ALTER TABLE public.nameproduct OWNER TO postgres;

--
-- Name: nameproduct_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nameproduct_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nameproduct_id_seq OWNER TO postgres;

--
-- Name: nameproduct_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nameproduct_id_seq OWNED BY public.nameproduct.id;


--
-- Name: orderclient; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orderclient (
    numborder integer NOT NULL,
    clientid integer NOT NULL,
    datecreate date NOT NULL,
    numbwarehouse integer,
    idmanager integer NOT NULL
);


ALTER TABLE public.orderclient OWNER TO postgres;

--
-- Name: orderclient_numborder_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orderclient_numborder_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orderclient_numborder_seq OWNER TO postgres;

--
-- Name: orderclient_numborder_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orderclient_numborder_seq OWNED BY public.orderclient.numborder;


--
-- Name: products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.products (
    id integer NOT NULL,
    idname integer NOT NULL,
    priceproduct integer NOT NULL,
    discount integer,
    idcategory integer NOT NULL,
    oder integer DEFAULT 0
);


ALTER TABLE public.products OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_id_seq OWNER TO postgres;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: timediscount; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.timediscount (
    id integer NOT NULL,
    size smallint NOT NULL,
    datestart date,
    datefinish date,
    sizeorder integer
);


ALTER TABLE public.timediscount OWNER TO postgres;

--
-- Name: timediscount_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.timediscount_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.timediscount_id_seq OWNER TO postgres;

--
-- Name: timediscount_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.timediscount_id_seq OWNED BY public.timediscount.id;


--
-- Name: volumeware; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.volumeware (
    idproduct integer NOT NULL,
    numborder integer NOT NULL,
    volume integer NOT NULL
);


ALTER TABLE public.volumeware OWNER TO postgres;

--
-- Name: volumewarehouse; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.volumewarehouse (
    idproduct integer NOT NULL,
    numbwarehouse integer NOT NULL,
    volume integer NOT NULL
);


ALTER TABLE public.volumewarehouse OWNER TO postgres;

--
-- Name: warehouse; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.warehouse (
    numb integer NOT NULL,
    numbertelephone character(12) NOT NULL
);


ALTER TABLE public.warehouse OWNER TO postgres;

--
-- Name: warehouse_numb_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.warehouse_numb_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.warehouse_numb_seq OWNER TO postgres;

--
-- Name: warehouse_numb_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.warehouse_numb_seq OWNED BY public.warehouse.numb;


--
-- Name: addresclient id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.addresclient ALTER COLUMN id SET DEFAULT nextval('public.addresclent_id_seq'::regclass);


--
-- Name: associations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.associations ALTER COLUMN id SET DEFAULT nextval('public.associations_id_seq'::regclass);


--
-- Name: nameproduct id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nameproduct ALTER COLUMN id SET DEFAULT nextval('public.nameproduct_id_seq'::regclass);


--
-- Name: orderclient numborder; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orderclient ALTER COLUMN numborder SET DEFAULT nextval('public.orderclient_numborder_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: subcategory id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subcategory ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: timediscount id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.timediscount ALTER COLUMN id SET DEFAULT nextval('public.timediscount_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.client_id_seq'::regclass);


--
-- Name: warehouse numb; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.warehouse ALTER COLUMN numb SET DEFAULT nextval('public.warehouse_numb_seq'::regclass);


--
-- Data for Name: addresclient; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.addresclient (id, arealife, cityliie, street, numberhouse, numberflat) FROM stdin;
\.


--
-- Data for Name: addreswarehouse; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.addreswarehouse (idwarehouse, area, city, street, numberhouse, numberflat) FROM stdin;
\.


--
-- Data for Name: associationproduct; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.associationproduct (idnameproduct, idassociation) FROM stdin;
\.


--
-- Data for Name: associations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.associations (id, imageassociation) FROM stdin;
\.


--
-- Data for Name: category; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.category (id, namecategory) FROM stdin;
1	чай
2	другое
3	к чаю
\.


--
-- Data for Name: clientinfo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.clientinfo (clientid, addresid, numbertelephone, name, surname, patronymic) FROM stdin;
\.


--
-- Data for Name: discountclient; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.discountclient (idclient, iddiscount, usecount) FROM stdin;
\.


--
-- Data for Name: listsubcategories; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.listsubcategories (idproduct, idcategory) FROM stdin;
\.


--
-- Data for Name: managerwarehouse; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.managerwarehouse (idmanager, numbwarehouse) FROM stdin;
\.


--
-- Data for Name: markclient; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.markclient (idclient, idproduct, marks, komment) FROM stdin;
\.


--
-- Data for Name: nameproduct; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nameproduct (id, nameitem, description, imageitem) FROM stdin;
1	Чай Обычный	Текст 	\N
2	Чай Индийский	Текст про чай Индийский скопированный несколько разТекст про чай Индийский скопированный несколько разТекст про чай Индийский скопированный несколько разТекст про чай Индийский скопированный несколько разТекст про чай Индийский скопированный несколько разТекст про чай Индийский скопированный несколько раз 	\N
3	Чай Китайский	Текст про чай Китайски скопированный несколько разТекст про чай Китайски скопированный несколько разТекст про чай Китайски скопированный несколько разТекст про чай Китайски скопированный несколько разТекст про чай Китайски скопированный несколько раз	\N
4	Не чай	Текст про не чай  скопированный несколько разТекст про не чай  скопированный несколько разТекст про не чай  скопированный несколько разТекст про не чай  скопированный несколько разТекст про не чай  скопированный несколько раз	\N
5	Сахар	Текст про Сахар  скопированный несколько раз Текст про не чай  скопированный несколько раз Текст про не чай  скопированный несколько раз Текст про не чай  скопированный несколько раз	\N
6	Мармелад	Текст про Мармелад  скопированный несколько разТекст про Мармелад  скопированный несколько разТекст про Мармелад  скопированный несколько разТекст про Мармелад  скопированный несколько раз	\N
\.


--
-- Data for Name: orderclient; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orderclient (numborder, clientid, datecreate, numbwarehouse, idmanager) FROM stdin;
\.


--
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.products (id, idname, priceproduct, discount, idcategory, oder) FROM stdin;
1	1	100	\N	1	0
2	2	120	\N	1	0
3	3	130	\N	1	0
4	4	140	\N	2	0
5	5	150	\N	3	0
6	6	160	\N	3	0
\.


--
-- Data for Name: subcategory; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subcategory (id, namecategoryi, idtimediscount) FROM stdin;
\.


--
-- Data for Name: timediscount; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.timediscount (id, size, datestart, datefinish, sizeorder) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, datereg) FROM stdin;
\.


--
-- Data for Name: volumeware; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.volumeware (idproduct, numborder, volume) FROM stdin;
\.


--
-- Data for Name: volumewarehouse; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.volumewarehouse (idproduct, numbwarehouse, volume) FROM stdin;
\.


--
-- Data for Name: warehouse; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.warehouse (numb, numbertelephone) FROM stdin;
\.


--
-- Name: addresclent_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.addresclent_id_seq', 1, false);


--
-- Name: associations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.associations_id_seq', 1, false);


--
-- Name: categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.categories_id_seq', 1, false);


--
-- Name: client_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_id_seq', 1, false);


--
-- Name: nameproduct_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nameproduct_id_seq', 30, true);


--
-- Name: orderclient_numborder_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orderclient_numborder_seq', 1, false);


--
-- Name: products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.products_id_seq', 6, true);


--
-- Name: timediscount_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.timediscount_id_seq', 1, false);


--
-- Name: warehouse_numb_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.warehouse_numb_seq', 1, false);


--
-- Name: addresclient addresclent_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.addresclient
    ADD CONSTRAINT addresclent_pkey PRIMARY KEY (id);


--
-- Name: addreswarehouse addreswarehouse_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.addreswarehouse
    ADD CONSTRAINT addreswarehouse_pkey PRIMARY KEY (idwarehouse);


--
-- Name: associationproduct associationproduct_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.associationproduct
    ADD CONSTRAINT associationproduct_pkey PRIMARY KEY (idnameproduct, idassociation);


--
-- Name: associations associations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.associations
    ADD CONSTRAINT associations_pkey PRIMARY KEY (id);


--
-- Name: subcategory categories_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subcategory
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: listsubcategories categoriesproduct_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.listsubcategories
    ADD CONSTRAINT categoriesproduct_pkey PRIMARY KEY (idproduct, idcategory);


--
-- Name: category category_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.category
    ADD CONSTRAINT category_pkey PRIMARY KEY (id);


--
-- Name: users client_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT client_pkey PRIMARY KEY (id);


--
-- Name: clientinfo clientinfo_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientinfo
    ADD CONSTRAINT clientinfo_pkey PRIMARY KEY (clientid);


--
-- Name: discountclient discountclient_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.discountclient
    ADD CONSTRAINT discountclient_pkey PRIMARY KEY (idclient, iddiscount);


--
-- Name: managerwarehouse managerwarehouse_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.managerwarehouse
    ADD CONSTRAINT managerwarehouse_pkey PRIMARY KEY (idmanager, numbwarehouse);


--
-- Name: markclient markclient_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.markclient
    ADD CONSTRAINT markclient_pkey PRIMARY KEY (idclient, idproduct);


--
-- Name: nameproduct nameproduct_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nameproduct
    ADD CONSTRAINT nameproduct_pkey PRIMARY KEY (id);


--
-- Name: orderclient orderclient_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orderclient
    ADD CONSTRAINT orderclient_pkey PRIMARY KEY (numborder);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: timediscount timediscount_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.timediscount
    ADD CONSTRAINT timediscount_pkey PRIMARY KEY (id);


--
-- Name: volumeware volumeware_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumeware
    ADD CONSTRAINT volumeware_pkey PRIMARY KEY (idproduct, numborder);


--
-- Name: volumewarehouse volumewarehouse_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumewarehouse
    ADD CONSTRAINT volumewarehouse_pkey PRIMARY KEY (idproduct, numbwarehouse);


--
-- Name: warehouse warehouse_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.warehouse
    ADD CONSTRAINT warehouse_pkey PRIMARY KEY (numb);


--
-- Name: addreswarehouse fk_addres_warehouse; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.addreswarehouse
    ADD CONSTRAINT fk_addres_warehouse FOREIGN KEY (idwarehouse) REFERENCES public.warehouse(numb);


--
-- Name: clientinfo fk_adresclent_client; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientinfo
    ADD CONSTRAINT fk_adresclent_client FOREIGN KEY (addresid) REFERENCES public.addresclient(id);


--
-- Name: associationproduct fk_association_nameproduct; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.associationproduct
    ADD CONSTRAINT fk_association_nameproduct FOREIGN KEY (idassociation) REFERENCES public.associations(id);


--
-- Name: discountclient fk_client_discount; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.discountclient
    ADD CONSTRAINT fk_client_discount FOREIGN KEY (idclient) REFERENCES public.users(id);


--
-- Name: markclient fk_client_mark; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.markclient
    ADD CONSTRAINT fk_client_mark FOREIGN KEY (idclient) REFERENCES public.users(id);


--
-- Name: orderclient fk_client_order; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orderclient
    ADD CONSTRAINT fk_client_order FOREIGN KEY (clientid) REFERENCES public.users(id);


--
-- Name: clientinfo fk_clientinfo_client; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientinfo
    ADD CONSTRAINT fk_clientinfo_client FOREIGN KEY (clientid) REFERENCES public.users(id);


--
-- Name: discountclient fk_discount_client; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.discountclient
    ADD CONSTRAINT fk_discount_client FOREIGN KEY (iddiscount) REFERENCES public.timediscount(id);


--
-- Name: listsubcategories fk_listsubcategories_products; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.listsubcategories
    ADD CONSTRAINT fk_listsubcategories_products FOREIGN KEY (idproduct) REFERENCES public.products(id);


--
-- Name: listsubcategories fk_listsubcategories_subcategories; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.listsubcategories
    ADD CONSTRAINT fk_listsubcategories_subcategories FOREIGN KEY (idcategory) REFERENCES public.subcategory(id);


--
-- Name: orderclient fk_manager_order; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orderclient
    ADD CONSTRAINT fk_manager_order FOREIGN KEY (idmanager) REFERENCES public.users(id);


--
-- Name: managerwarehouse fk_manager_warehouse; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.managerwarehouse
    ADD CONSTRAINT fk_manager_warehouse FOREIGN KEY (idmanager) REFERENCES public.users(id);


--
-- Name: associationproduct fk_nameproduct_association; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.associationproduct
    ADD CONSTRAINT fk_nameproduct_association FOREIGN KEY (idnameproduct) REFERENCES public.nameproduct(id);


--
-- Name: volumeware fk_order_volume; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumeware
    ADD CONSTRAINT fk_order_volume FOREIGN KEY (numborder) REFERENCES public.orderclient(numborder);


--
-- Name: markclient fk_product_mark; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.markclient
    ADD CONSTRAINT fk_product_mark FOREIGN KEY (idproduct) REFERENCES public.products(id);


--
-- Name: products fk_product_name; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT fk_product_name FOREIGN KEY (id) REFERENCES public.nameproduct(id) ON DELETE CASCADE;


--
-- Name: volumeware fk_product_volume; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumeware
    ADD CONSTRAINT fk_product_volume FOREIGN KEY (idproduct) REFERENCES public.products(id);


--
-- Name: volumewarehouse fk_product_volumewarehouse; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumewarehouse
    ADD CONSTRAINT fk_product_volumewarehouse FOREIGN KEY (idproduct) REFERENCES public.products(id);


--
-- Name: products fk_products_category; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT fk_products_category FOREIGN KEY (idcategory) REFERENCES public.category(id);


--
-- Name: subcategory fk_subcategory_timediscount; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subcategory
    ADD CONSTRAINT fk_subcategory_timediscount FOREIGN KEY (idtimediscount) REFERENCES public.timediscount(id);


--
-- Name: managerwarehouse fk_warehouse_manager; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.managerwarehouse
    ADD CONSTRAINT fk_warehouse_manager FOREIGN KEY (numbwarehouse) REFERENCES public.warehouse(numb);


--
-- Name: orderclient fk_warehouse_order; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orderclient
    ADD CONSTRAINT fk_warehouse_order FOREIGN KEY (numbwarehouse) REFERENCES public.warehouse(numb);


--
-- Name: volumewarehouse fk_warehouse_volumewarehouse; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.volumewarehouse
    ADD CONSTRAINT fk_warehouse_volumewarehouse FOREIGN KEY (numbwarehouse) REFERENCES public.warehouse(numb);


--
-- PostgreSQL database dump complete
--

