CREATE TABLE produtos_base (
                               prod_id INTEGER PRIMARY KEY AUTOINCREMENT,
                               prod_cod VARCHAR(30),
                               prod_nome VARCHAR(150),
                               prod_cat VARCHAR(50),
                               prod_subcat VARCHAR(50),
                               prod_desc TEXT,
                               prod_fab VARCHAR(100),
                               prod_mod VARCHAR(50),
                               prod_cor VARCHAR(30),
                               prod_peso TEXT,
                               prod_larg TEXT,
                               prod_alt TEXT,
                               prod_prof TEXT,
                               prod_und VARCHAR(10),
                               prod_atv BOOLEAN,
                               prod_dt_cad TEXT
);

INSERT INTO produtos_base (
    prod_cod, prod_nome, prod_cat, prod_subcat, prod_desc, prod_fab,
    prod_mod, prod_cor, prod_peso, prod_larg, prod_alt, prod_prof, prod_und, prod_atv, prod_dt_cad
) VALUES
      (' PRD001', '   Teclado  Mecânico   RGB  ', 'PERIFERICOS', 'TECLADOS', 'Teclado com iluminação RGB e switches azuis', 'HyperTech', 'HT-KEY-RGB', 'Preto', '1,2kg ', '45 cm', '5 CM ', '15cm ', 'UN ', TRUE, '2025/10/10'),
      ('prd002 ', ' Mouse óptico  sem fio', 'Periféricos ', 'Mouses', 'Mouse com conexão wireless 2.4GHz', 'TechPro', 'TP-MOUSE-X', ' Preto ', ' 95g ', '6cm', '3 CM', '10cm', ' un', TRUE, '10-10-2025'),
      ('PRD003', 'Monitor 24" Full HD', 'MONITORES', 'LCD', 'Monitor 1080p HDMI e VGA', 'ViewBest', 'VB-24HD', 'Preto', '3.5 KG', '53,2cm ', '32cm ', ' 12 cm ', 'un', TRUE, '2025-10-12'),
      ('PRD004', 'Cadeira gamer Reclinável ', 'Móveis ', 'Cadeiras', 'Cadeira ergonômica com apoio de braço', 'ComfortSeat', 'CS-GAMER-R', 'Vermelha', ' 16,3 kg', '70 CM ', ' 120 cm', '60 CM', 'UN', TRUE, '15/10/2025'),
      (' PRD005', 'HEADSET gamer 7.1', 'Periféricos', 'Headsets', 'Headset com som surround 7.1 e microfone retrátil', 'SoundMax', 'SM-HS71', ' Preto ', ' 350G ', '18cm', '20 CM ', '10 cm', 'UN', FALSE, '2025.10.09'),
      ('PRD006', 'SSD 1TB NVMe', 'COMPONENTES', 'ARMAZENAMENTO', 'SSD NVMe M.2 2280, leitura 3500MB/s', 'DataFast', 'DF-NV1TB', 'Prata', '8g', '8cm', '0,2cm', '2,2cm', 'UN', TRUE, '2023-05-20'),
      ('PRD007', 'Placa de Vídeo RTX 4060', 'COMPONENTES', 'GPUS', '8GB GDDR6, 3072 cores CUDA', 'GraphiCore', 'GC-RTX4060', 'Preto', '1.2kg', '24cm', '11.5cm', '4cm', 'UN', TRUE, '2024/01/15'),
      ('PRD008', 'Processador Core i7', 'COMPONENTES', 'CPUS', '12ª geração, 12 núcleos, 4.9GHz', 'ChipTech', 'CT-I712700', 'Prata', '50g', '4.5cm', '4.5cm', '0.5cm', 'UN', TRUE, '2023.11.30'),
      ('PRD009', 'Memória RAM 16GB', 'COMPONENTES', 'MEMORIAS', 'DDR4 3200MHz, CL16', 'MemSpeed', 'MS-D416G', 'Preta', '40g', '13.3cm', '3cm', '0.8cm', 'UN', TRUE, '2023/08/22'),
      ('PRD010', 'Fonte 750W 80 Plus', 'COMPONENTES', 'FONTES', 'Modular, certificação Gold', 'PowerMax', 'PM-750G', 'Preta', '2.1kg', '16cm', '15cm', '8.6cm', 'UN', TRUE, '2024-02-10'),
      ('PRD011', 'Gabinete Gamer', 'ACESSORIOS', 'GABINETES', 'Mid Tower, com lateral em vidro', 'CaseMaster', 'CM-T500', 'Branco', '6.5kg', '47cm', '50cm', '22cm', 'UN', TRUE, '2023.12.05'),
      ('PRD012', 'Water Cooler 240mm', 'COMPONENTES', 'REFRIAMENTO', 'Radiador duplo, RGB', 'CoolFlow', 'CF-W240', 'Preto', '1.1kg', '27.5cm', '12cm', '5.2cm', 'UN', FALSE, '2024/03/18');

CREATE TABLE precos_base (
                             preco_id INTEGER PRIMARY KEY AUTOINCREMENT,
                             prc_cod_prod VARCHAR(30),
                             prc_valor TEXT,
                             prc_moeda VARCHAR(10),
                             prc_desc TEXT,
                             prc_acres TEXT,
                             prc_promo TEXT,
                             prc_dt_ini_promo TEXT,
                             prc_dt_fim_promo TEXT,
                             prc_dt_atual TEXT,
                             prc_origem VARCHAR(50),
                             prc_tipo_cli VARCHAR(30),
                             prc_vend_resp VARCHAR(100),
                             prc_obs TEXT,
                             prc_status VARCHAR(20)
);

INSERT INTO precos_base (
    prc_cod_prod, prc_valor, prc_moeda, prc_desc, prc_acres, prc_promo,
    prc_dt_ini_promo, prc_dt_fim_promo, prc_dt_atual, prc_origem,
    prc_tipo_cli, prc_vend_resp, prc_obs, prc_status
) VALUES
      (' PRD001 ', ' 499,90 ', 'brl', '5%', '0', '474,90', '2025/10/10', '2025-10-20', '2025-10-15', 'SISTEMA ERP', 'VAREJO', 'Marcos Silva', 'Produto em destaque', 'ativo'),
      ('prd002', '120.50', 'BRL ', ' 0', '0', '120.50', '10-10-2025', NULL, '2025-10-16', 'MIGRACAO', 'ATACADO', ' Julia S.', NULL, 'ativo'),
      ('PRD003', '1.099,00', 'brl ', '10%', NULL, '989,10', '2025.10.10', '2025.10.25', '16/10/2025', 'API INTERNA', 'VAREJO', ' Carlos Souza ', 'Desconto aplicado via API', 'ativo'),
      (' prd004', ' 899.99', 'brl', NULL, '5%', NULL, '15/10/2025', '30/10/2025', '2025/10/16', 'ERP LEGADO', 'VAREJO', 'Jéssica M.', 'Campanha de Outubro', 'ativo'),
      ('PRD005 ', 'sem preço', 'BRL', NULL, NULL, NULL, NULL, NULL, '2025-10-10', 'ERP LEGADO', 'VAREJO', 'Pedro L.', 'Sem preço cadastrado', 'inativo'),
      ('PRD006', '389,90', 'BRL', '15%', '2%', '331,42', '2024.06.01', '2024.06.30', '2024-05-28', 'SISTEMA ERP', 'VAREJO', 'Ana Costa', 'Promoção semestral', 'ativo'),
      ('PRD007', '2.899,00', 'brl', '8%', NULL, '2.667,08', '2024/04/15', '2024/05/15', '2024-04-10', 'API INTERNA', 'ATACADO', 'Roberto Lima', 'Desconto por volume', 'ativo'),
      ('PRD008', '1.899,00', 'BRL', '12%', '3%', '1.671,12', '2024-03-01', '2024-03-31', '2024.02.25', 'ERP LEGADO', 'VAREJO', 'Fernanda R.', 'Liquidação estoque', 'ativo'),
      ('PRD009', '299,90', 'brl', '20%', '0', '239,92', '2024.05.20', '2024.06.20', '2024/05/15', 'SISTEMA ERP', 'ATACADO', 'Carlos Santos', 'Queima de estoque', 'ativo'),
      ('PRD010', '699,00', 'BRL', '5%', '1%', '664,05', '2024-04-01', '2024-04-30', '2024-03-28', 'API INTERNA', 'VAREJO', 'Mariana O.', 'Promoção relâmpago', 'ativo'),
      ('PRD011', '450,00', 'brl', '25%', NULL, '337,50', '2024/06/10', '2024/07/10', '2024.06.05', 'ERP LEGADO', 'VAREJO', 'Paulo H.', 'Dia dos namorados', 'ativo'),
      ('PRD012', '0', 'BRL', NULL, NULL, NULL, NULL, NULL, '2024-03-20', 'SISTEMA ERP', 'VAREJO', 'Lucas T.', 'Produto descontinuado', 'inativo');