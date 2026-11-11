-- Add 100 extended users (BS100400-BS100499) - SIMPLIFIED VERSION

-- First, verify we start fresh for BS1004xx
DELETE FROM users WHERE customer_id LIKE 'BS1004%';

-- Insert 100 users with basic data
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100400', 'Quincy Thomas', 'quincy.thomas.BS100400@banksploit.local', '+919900000400', '$2y$10$quincy0001', MD5('BS100400B4rc@123'), 'XXXX-XXXX-0400', 'QUI0400', '1 MG Road, Bangalore', 'user', 0, 0, 0, '192.168.1.200', 'available', NOW()),
('BS100401', 'Rajesh Bhandari', 'rajesh.bhandari.BS100401@banksploit.local', '+919900000401', '$2y$10$rajesh0001', MD5('BS100401B4rc@123'), 'XXXX-XXXX-0401', 'RAJ0401', '2 Indiranagar, Bangalore', 'user', 0, 0, 0, '192.168.1.201', 'available', NOW()),
('BS100402', 'Sneha Iyer', 'sneha.iyer.BS100402@banksploit.local', '+919900000402', '$2y$10$sneha0001', MD5('BS100402B4rc@123'), 'XXXX-XXXX-0402', 'SNE0402', '3 Koramangala, Bangalore', 'user', 0, 0, 0, '192.168.1.202', 'available', NOW()),
('BS100403', 'Tarun Misra', 'tarun.misra.BS100403@banksploit.local', '+919900000403', '$2y$10$tarun0001', MD5('BS100403B4rc@123'), 'XXXX-XXXX-0403', 'TAR0403', '4 Whitefield, Bangalore', 'user', 0, 0, 0, '192.168.1.203', 'available', NOW()),
('BS100404', 'Usha Patel', 'usha.patel.BS100404@banksploit.local', '+919900000404', '$2y$10$usha0001', MD5('BS100404B4rc@123'), 'XXXX-XXXX-0404', 'USA0404', '5 Marathahalli, Bangalore', 'user', 0, 0, 0, '192.168.1.204', 'available', NOW()),
('BS100405', 'Varun Malhi', 'varun.malhi.BS100405@banksploit.local', '+919900000405', '$2y$10$varun0001', MD5('BS100405B4rc@123'), 'XXXX-XXXX-0405', 'VAR0405', '6 JP Nagar, Bangalore', 'user', 0, 0, 0, '192.168.1.205', 'available', NOW()),
('BS100406', 'Wanda Desai', 'wanda.desai.BS100406@banksploit.local', '+919900000406', '$2y$10$wanda0001', MD5('BS100406B4rc@123'), 'XXXX-XXXX-0406', 'WAN0406', '7 Hebbal, Bangalore', 'user', 0, 0, 0, '192.168.1.206', 'available', NOW()),
('BS100407', 'Xenophon Pillai', 'xenophon.pillai.BS100407@banksploit.local', '+919900000407', '$2y$10$xenophon01', MD5('BS100407B4rc@123'), 'XXXX-XXXX-0407', 'XEN0407', '8 Jayanagar, Bangalore', 'user', 0, 0, 0, '192.168.1.207', 'available', NOW()),
('BS100408', 'Yara Khan', 'yara.khan.BS100408@banksploit.local', '+919900000408', '$2y$10$yara0001', MD5('BS100408B4rc@123'), 'XXXX-XXXX-0408', 'YAR0408', '9 Yeshwanthpur, Bangalore', 'user', 0, 0, 0, '192.168.1.208', 'available', NOW()),
('BS100409', 'Zara Sharma', 'zara.sharma.BS100409@banksploit.local', '+919900000409', '$2y$10$zara0001', MD5('BS100409B4rc@123'), 'XXXX-XXXX-0409', 'ZAR0409', '10 Malleswaram, Bangalore', 'user', 0, 0, 0, '192.168.1.209', 'available', NOW()),
('BS100410', 'Aarav Singh', 'aarav.singh.BS100410@banksploit.local', '+919900000410', '$2y$10$aarav0001', MD5('BS100410B4rc@123'), 'XXXX-XXXX-0410', 'AAR0410', '11 Rajajinagar, Bangalore', 'user', 0, 0, 0, '192.168.1.210', 'available', NOW()),
('BS100411', 'Abha Menon', 'abha.menon.BS100411@banksploit.local', '+919900000411', '$2y$10$abha0001', MD5('BS100411B4rc@123'), 'XXXX-XXXX-0411', 'ABH0411', '12 Basavanagudi, Bangalore', 'user', 0, 0, 0, '192.168.1.211', 'available', NOW()),
('BS100412', 'Abhi Chopra', 'abhi.chopra.BS100412@banksploit.local', '+919900000412', '$2y$10$abhi0001', MD5('BS100412B4rc@123'), 'XXXX-XXXX-0412', 'ABH0412', '13 Vijayanagar, Bangalore', 'user', 0, 0, 0, '192.168.1.212', 'available', NOW()),
('BS100413', 'Achit Verma', 'achit.verma.BS100413@banksploit.local', '+919900000413', '$2y$10$achit0001', MD5('BS100413B4rc@123'), 'XXXX-XXXX-0413', 'ACH0413', '14 KR Puram, Bangalore', 'user', 0, 0, 0, '192.168.1.213', 'available', NOW()),
('BS100414', 'Adnan Patel', 'adnan.patel.BS100414@banksploit.local', '+919900000414', '$2y$10$adnan0001', MD5('BS100414B4rc@123'), 'XXXX-XXXX-0414', 'ADN0414', '15 Sadashivnagar, Bangalore', 'user', 0, 0, 0, '192.168.1.214', 'available', NOW()),
('BS100415', 'Aditya Rao', 'aditya.rao.BS100415@banksploit.local', '+919900000415', '$2y$10$aditya0001', MD5('BS100415B4rc@123'), 'XXXX-XXXX-0415', 'ADI0415', '16 Fort, Bangalore', 'user', 0, 0, 0, '192.168.1.215', 'available', NOW());

-- Continue with remaining 84 users...
-- (Due to SQL length limitations, adding in batches of 10)

INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100416', 'Afi Khan', 'afi.khan.BS100416@banksploit.local', '+919900000416', '$2y$10$afi0001', MD5('BS100416B4rc@123'), 'XXXX-XXXX-0416', 'AFI0416', '17 Banashankari, Bangalore', 'user', 0, 0, 0, '192.168.1.216', 'available', NOW()),
('BS100417', 'Agni Singh', 'agni.singh.BS100417@banksploit.local', '+919900000417', '$2y$10$agni0001', MD5('BS100417B4rc@123'), 'XXXX-XXXX-0417', 'AGN0417', '18 Ulsoor, Bangalore', 'user', 0, 0, 0, '192.168.1.217', 'available', NOW()),
('BS100418', 'Ahana Desai', 'ahana.desai.BS100418@banksploit.local', '+919900000418', '$2y$10$ahana0001', MD5('BS100418B4rc@123'), 'XXXX-XXXX-0418', 'AHA0418', '19 Ramamurthy Nagar, Bangalore', 'user', 0, 0, 0, '192.168.1.218', 'available', NOW()),
('BS100419', 'Aira Iyer', 'aira.iyer.BS100419@banksploit.local', '+919900000419', '$2y$10$aira0001', MD5('BS100419B4rc@123'), 'XXXX-XXXX-0419', 'AIR0419', '20 Ulsoor Lake, Bangalore', 'user', 0, 0, 0, '192.168.1.219', 'available', NOW()),
('BS100420', 'Aja Malhotra', 'aja.malhotra.BS100420@banksploit.local', '+919900000420', '$2y$10$aja0001', MD5('BS100420B4rc@123'), 'XXXX-XXXX-0420', 'AJA0420', '21 Tank Bund Road, Bangalore', 'user', 0, 0, 0, '192.168.1.220', 'available', NOW()),
('BS100421', 'Ajay Singh', 'ajay.singh.BS100421@banksploit.local', '+919900000421', '$2y$10$ajay0001', MD5('BS100421B4rc@123'), 'XXXX-XXXX-0421', 'AJA0421', '22 Oxford School Road, Bangalore', 'user', 0, 0, 0, '192.168.1.221', 'available', NOW()),
('BS100422', 'Ajit Kumar', 'ajit.kumar.BS100422@banksploit.local', '+919900000422', '$2y$10$ajit0001', MD5('BS100422B4rc@123'), 'XXXX-XXXX-0422', 'AJI0422', '23 Varthur, Bangalore', 'user', 0, 0, 0, '192.168.1.222', 'available', NOW()),
('BS100423', 'Akbar Khan', 'akbar.khan.BS100423@banksploit.local', '+919900000423', '$2y$10$akbar0001', MD5('BS100423B4rc@123'), 'XXXX-XXXX-0423', 'AKB0423', '24 Sarjapur, Bangalore', 'user', 0, 0, 0, '192.168.1.223', 'available', NOW()),
('BS100424', 'Akil Verma', 'akil.verma.BS100424@banksploit.local', '+919900000424', '$2y$10$akil0001', MD5('BS100424B4rc@123'), 'XXXX-XXXX-0424', 'AKI0424', '25 Bellandur, Bangalore', 'user', 0, 0, 0, '192.168.1.224', 'available', NOW()),
('BS100425', 'Akira Patel', 'akira.patel.BS100425@banksploit.local', '+919900000425', '$2y$10$akira0001', MD5('BS100425B4rc@123'), 'XXXX-XXXX-0425', 'AKI0425', '26 Kundalahalli, Bangalore', 'user', 0, 0, 0, '192.168.1.225', 'available', NOW());

-- Continue with batch 3 (BS100426-BS100435)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100426', 'Alak Bhattacharya', 'alak.bhattacharya.BS100426@banksploit.local', '+919900000426', '$2y$10$alak0001', MD5('BS100426B4rc@123'), 'XXXX-XXXX-0426', 'ALA0426', '27 Gottigere, Bangalore', 'user', 0, 0, 0, '192.168.1.226', 'available', NOW()),
('BS100427', 'Alam Khan', 'alam.khan.BS100427@banksploit.local', '+919900000427', '$2y$10$alam0001', MD5('BS100427B4rc@123'), 'XXXX-XXXX-0427', 'ALA0427', '28 Bannerghatta, Bangalore', 'user', 0, 0, 0, '192.168.1.227', 'available', NOW()),
('BS100428', 'Alana Verma', 'alana.verma.BS100428@banksploit.local', '+919900000428', '$2y$10$alana0001', MD5('BS100428B4rc@123'), 'XXXX-XXXX-0428', 'ALA0428', '29 Budihal, Bangalore', 'user', 0, 0, 0, '192.168.1.228', 'available', NOW()),
('BS100429', 'Alani Singh', 'alani.singh.BS100429@banksploit.local', '+919900000429', '$2y$10$alani0001', MD5('BS100429B4rc@123'), 'XXXX-XXXX-0429', 'ALA0429', '30 Madivala, Bangalore', 'user', 0, 0, 0, '192.168.1.229', 'available', NOW()),
('BS100430', 'Alanna Patel', 'alanna.patel.BS100430@banksploit.local', '+919900000430', '$2y$10$alanna01', MD5('BS100430B4rc@123'), 'XXXX-XXXX-0430', 'ALA0430', '31 Jigani, Bangalore', 'user', 0, 0, 0, '192.168.1.230', 'available', NOW()),
('BS100431', 'Alaric Desai', 'alaric.desai.BS100431@banksploit.local', '+919900000431', '$2y$10$alaric01', MD5('BS100431B4rc@123'), 'XXXX-XXXX-0431', 'ALA0431', '32 Thippagondanahalli, Bangalore', 'user', 0, 0, 0, '192.168.1.231', 'available', NOW()),
('BS100432', 'Alarick Iyer', 'alarick.iyer.BS100432@banksploit.local', '+919900000432', '$2y$10$alarick1', MD5('BS100432B4rc@123'), 'XXXX-XXXX-0432', 'ALA0432', '33 Kanakapura, Bangalore', 'user', 0, 0, 0, '192.168.1.232', 'available', NOW()),
('BS100433', 'Alaric Pillai', 'alaric.pillai.BS100433@banksploit.local', '+919900000433', '$2y$10$alaric02', MD5('BS100433B4rc@123'), 'XXXX-XXXX-0433', 'ALA0433', '34 Channapatna, Bangalore', 'user', 0, 0, 0, '192.168.1.233', 'available', NOW()),
('BS100434', 'Alas Kumar', 'alas.kumar.BS100434@banksploit.local', '+919900000434', '$2y$10$alas0001', MD5('BS100434B4rc@123'), 'XXXX-XXXX-0434', 'ALA0434', '35 Ramanagara, Bangalore', 'user', 0, 0, 0, '192.168.1.234', 'available', NOW()),
('BS100435', 'Alase Verma', 'alase.verma.BS100435@banksploit.local', '+919900000435', '$2y$10$alase0001', MD5('BS100435B4rc@123'), 'XXXX-XXXX-0435', 'ALA0435', '36 Kolar, Bangalore', 'user', 0, 0, 0, '192.168.1.235', 'available', NOW());

-- Continue with batch 4 (BS100436-BS100445)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100436', 'Alastair Singh', 'alastair.singh.BS100436@banksploit.local', '+919900000436', '$2y$10$alastair1', MD5('BS100436B4rc@123'), 'XXXX-XXXX-0436', 'ALA0436', '37 Chintamani, Bangalore', 'user', 0, 0, 0, '192.168.1.236', 'available', NOW()),
('BS100437', 'Alayna Patel', 'alayna.patel.BS100437@banksploit.local', '+919900000437', '$2y$10$alayna01', MD5('BS100437B4rc@123'), 'XXXX-XXXX-0437', 'ALA0437', '38 Gauribidanur, Bangalore', 'user', 0, 0, 0, '192.168.1.237', 'available', NOW()),
('BS100438', 'Alb Kumar', 'alb.kumar.BS100438@banksploit.local', '+919900000438', '$2y$10$alb00001', MD5('BS100438B4rc@123'), 'XXXX-XXXX-0438', 'ALB0438', '39 Chikballapur, Bangalore', 'user', 0, 0, 0, '192.168.1.238', 'available', NOW()),
('BS100439', 'Alba Singh', 'alba.singh.BS100439@banksploit.local', '+919900000439', '$2y$10$alba0001', MD5('BS100439B4rc@123'), 'XXXX-XXXX-0439', 'ALB0439', '40 Doddaballapur, Bangalore', 'user', 0, 0, 0, '192.168.1.239', 'available', NOW()),
('BS100440', 'Alban Verma', 'alban.verma.BS100440@banksploit.local', '+919900000440', '$2y$10$alban0001', MD5('BS100440B4rc@123'), 'XXXX-XXXX-0440', 'ALB0440', '41 Bagepalli, Bangalore', 'user', 0, 0, 0, '192.168.1.240', 'available', NOW()),
('BS100441', 'Albania Desai', 'albania.desai.BS100441@banksploit.local', '+919900000441', '$2y$10$albania01', MD5('BS100441B4rc@123'), 'XXXX-XXXX-0441', 'ALB0441', '42 Mulbagal, Bangalore', 'user', 0, 0, 0, '192.168.1.241', 'available', NOW()),
('BS100442', 'Albany Khan', 'albany.khan.BS100442@banksploit.local', '+919900000442', '$2y$10$albany01', MD5('BS100442B4rc@123'), 'XXXX-XXXX-0442', 'ALB0442', '43 Srinivaspur, Bangalore', 'user', 0, 0, 0, '192.168.1.242', 'available', NOW()),
('BS100443', 'Albar Iyer', 'albar.iyer.BS100443@banksploit.local', '+919900000443', '$2y$10$albar0001', MD5('BS100443B4rc@123'), 'XXXX-XXXX-0443', 'ALB0443', '44 Pavagada, Bangalore', 'user', 0, 0, 0, '192.168.1.243', 'available', NOW()),
('BS100444', 'Albarran Patel', 'albarran.patel.BS100444@banksploit.local', '+919900000444', '$2y$10$albarran1', MD5('BS100444B4rc@123'), 'XXXX-XXXX-0444', 'ALB0444', '45 Sira, Bangalore', 'user', 0, 0, 0, '192.168.1.244', 'available', NOW()),
('BS100445', 'Albasa Pillai', 'albasa.pillai.BS100445@banksploit.local', '+919900000445', '$2y$10$albasa01', MD5('BS100445B4rc@123'), 'XXXX-XXXX-0445', 'ALB0445', '46 Tumkur, Bangalore', 'user', 0, 0, 0, '192.168.1.245', 'available', NOW());

-- Continue with batch 5 (BS100446-BS100455)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100446', 'Albe Kumar', 'albe.kumar.BS100446@banksploit.local', '+919900000446', '$2y$10$albe0001', MD5('BS100446B4rc@123'), 'XXXX-XXXX-0446', 'ALB0446', '47 Davangere, Bangalore', 'user', 0, 0, 0, '192.168.1.246', 'available', NOW()),
('BS100447', 'Albee Singh', 'albee.singh.BS100447@banksploit.local', '+919900000447', '$2y$10$albee0001', MD5('BS100447B4rc@123'), 'XXXX-XXXX-0447', 'ALB0447', '48 Harihara, Bangalore', 'user', 0, 0, 0, '192.168.1.247', 'available', NOW()),
('BS100448', 'Albena Verma', 'albena.verma.BS100448@banksploit.local', '+919900000448', '$2y$10$albena01', MD5('BS100448B4rc@123'), 'XXXX-XXXX-0448', 'ALB0448', '49 Ranebennur, Bangalore', 'user', 0, 0, 0, '192.168.1.248', 'available', NOW()),
('BS100449', 'Alber Desai', 'alber.desai.BS100449@banksploit.local', '+919900000449', '$2y$10$alber0001', MD5('BS100449B4rc@123'), 'XXXX-XXXX-0449', 'ALB0449', '50 Byadagi, Bangalore', 'user', 0, 0, 0, '192.168.1.249', 'available', NOW()),
('BS100450', 'Alberta Khan', 'alberta.khan.BS100450@banksploit.local', '+919900000450', '$2y$10$alberta01', MD5('BS100450B4rc@123'), 'XXXX-XXXX-0450', 'ALB0450', '51 Amruthur, Bangalore', 'user', 0, 0, 0, '192.168.1.250', 'available', NOW()),
('BS100451', 'Albertha Iyer', 'albertha.iyer.BS100451@banksploit.local', '+919900000451', '$2y$10$albertha1', MD5('BS100451B4rc@123'), 'XXXX-XXXX-0451', 'ALB0451', '52 Shimoga, Bangalore', 'user', 0, 0, 0, '192.168.1.251', 'available', NOW()),
('BS100452', 'Albertina Patel', 'albertina.patel.BS100452@banksploit.local', '+919900000452', '$2y$10$albertina', MD5('BS100452B4rc@123'), 'XXXX-XXXX-0452', 'ALB0452', '53 Bhadravati, Bangalore', 'user', 0, 0, 0, '192.168.1.252', 'available', NOW()),
('BS100453', 'Albertine Pillai', 'albertine.pillai.BS100453@banksploit.local', '+919900000453', '$2y$10$albertine', MD5('BS100453B4rc@123'), 'XXXX-XXXX-0453', 'ALB0453', '54 Talikota, Bangalore', 'user', 0, 0, 0, '192.168.1.253', 'available', NOW()),
('BS100454', 'Albertino Kumar', 'albertino.kumar.BS100454@banksploit.local', '+919900000454', '$2y$10$albertino', MD5('BS100454B4rc@123'), 'XXXX-XXXX-0454', 'ALB0454', '55 Belgaum, Bangalore', 'user', 0, 0, 0, '192.168.1.254', 'available', NOW()),
('BS100455', 'Albertis Singh', 'albertis.singh.BS100455@banksploit.local', '+919900000455', '$2y$10$albertis1', MD5('BS100455B4rc@123'), 'XXXX-XXXX-0455', 'ALB0455', '56 Bailhongal, Bangalore', 'user', 0, 0, 0, '192.168.1.255', 'available', NOW());

-- Continue with batch 6 (BS100456-BS100465)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100456', 'Alberts Verma', 'alberts.verma.BS100456@banksploit.local', '+919900000456', '$2y$10$alberts01', MD5('BS100456B4rc@123'), 'XXXX-XXXX-0456', 'ALB0456', '57 Soundatti, Bangalore', 'user', 0, 0, 0, '192.168.2.1', 'available', NOW()),
('BS100457', 'Albertson Khan', 'albertson.khan.BS100457@banksploit.local', '+919900000457', '$2y$10$albertson', MD5('BS100457B4rc@123'), 'XXXX-XXXX-0457', 'ALB0457', '58 Gadag, Bangalore', 'user', 0, 0, 0, '192.168.2.2', 'available', NOW()),
('BS100458', 'Albertus Desai', 'albertus.desai.BS100458@banksploit.local', '+919900000458', '$2y$10$albertus1', MD5('BS100458B4rc@123'), 'XXXX-XXXX-0458', 'ALB0458', '59 Londa, Bangalore', 'user', 0, 0, 0, '192.168.2.3', 'available', NOW()),
('BS100459', 'Albertyn Iyer', 'albertyn.iyer.BS100459@banksploit.local', '+919900000459', '$2y$10$albertyn1', MD5('BS100459B4rc@123'), 'XXXX-XXXX-0459', 'ALB0459', '60 Nargund, Bangalore', 'user', 0, 0, 0, '192.168.2.4', 'available', NOW()),
('BS100460', 'Albertys Patel', 'albertys.patel.BS100460@banksploit.local', '+919900000460', '$2y$10$albertys1', MD5('BS100460B4rc@123'), 'XXXX-XXXX-0460', 'ALB0460', '61 Dambal, Bangalore', 'user', 0, 0, 0, '192.168.2.5', 'available', NOW()),
('BS100461', 'Albia Kumar', 'albia.kumar.BS100461@banksploit.local', '+919900000461', '$2y$10$albia0001', MD5('BS100461B4rc@123'), 'XXXX-XXXX-0461', 'ALB0461', '62 Yelburga, Bangalore', 'user', 0, 0, 0, '192.168.2.6', 'available', NOW()),
('BS100462', 'Albina Singh', 'albina.singh.BS100462@banksploit.local', '+919900000462', '$2y$10$albina01', MD5('BS100462B4rc@123'), 'XXXX-XXXX-0462', 'ALB0462', '63 Indi, Bangalore', 'user', 0, 0, 0, '192.168.2.7', 'available', NOW()),
('BS100463', 'Albinah Verma', 'albinah.verma.BS100463@banksploit.local', '+919900000463', '$2y$10$albinah01', MD5('BS100463B4rc@123'), 'XXXX-XXXX-0463', 'ALB0463', '64 Hirekerur, Bangalore', 'user', 0, 0, 0, '192.168.2.8', 'available', NOW()),
('BS100464', 'Albini Desai', 'albini.desai.BS100464@banksploit.local', '+919900000464', '$2y$10$albini01', MD5('BS100464B4rc@123'), 'XXXX-XXXX-0464', 'ALB0464', '65 Hanagal, Bangalore', 'user', 0, 0, 0, '192.168.2.9', 'available', NOW()),
('BS100465', 'Albino Pillai', 'albino.pillai.BS100465@banksploit.local', '+919900000465', '$2y$10$albino01', MD5('BS100465B4rc@123'), 'XXXX-XXXX-0465', 'ALB0465', '66 Ron, Bangalore', 'user', 0, 0, 0, '192.168.2.10', 'available', NOW());

-- Continue with batch 7 (BS100466-BS100475)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100466', 'Albion Kumar', 'albion.kumar.BS100466@banksploit.local', '+919900000466', '$2y$10$albion01', MD5('BS100466B4rc@123'), 'XXXX-XXXX-0466', 'ALB0466', '67 Londa, Bangalore', 'user', 0, 0, 0, '192.168.2.11', 'available', NOW()),
('BS100467', 'Albireo Singh', 'albireo.singh.BS100467@banksploit.local', '+919900000467', '$2y$10$albireo01', MD5('BS100467B4rc@123'), 'XXXX-XXXX-0467', 'ALB0467', '68 Bailhongal, Bangalore', 'user', 0, 0, 0, '192.168.2.12', 'available', NOW()),
('BS100468', 'Albiruni Verma', 'albiruni.verma.BS100468@banksploit.local', '+919900000468', '$2y$10$albiruni1', MD5('BS100468B4rc@123'), 'XXXX-XXXX-0468', 'ALB0468', '69 Hubli, Bangalore', 'user', 0, 0, 0, '192.168.2.13', 'available', NOW()),
('BS100469', 'Albo Desai', 'albo.desai.BS100469@banksploit.local', '+919900000469', '$2y$10$albo0001', MD5('BS100469B4rc@123'), 'XXXX-XXXX-0469', 'ALB0469', '70 Dharwad, Bangalore', 'user', 0, 0, 0, '192.168.2.14', 'available', NOW()),
('BS100470', 'Albons Khan', 'albons.khan.BS100470@banksploit.local', '+919900000470', '$2y$10$albons01', MD5('BS100470B4rc@123'), 'XXXX-XXXX-0470', 'ALB0470', '71 Belgaum, Bangalore', 'user', 0, 0, 0, '192.168.2.15', 'available', NOW()),
('BS100471', 'Alborz Iyer', 'alborz.iyer.BS100471@banksploit.local', '+919900000471', '$2y$10$alborz01', MD5('BS100471B4rc@123'), 'XXXX-XXXX-0471', 'ALB0471', '72 Dandeli, Bangalore', 'user', 0, 0, 0, '192.168.2.16', 'available', NOW()),
('BS100472', 'Alboyne Patel', 'alboyne.patel.BS100472@banksploit.local', '+919900000472', '$2y$10$alboyne01', MD5('BS100472B4rc@123'), 'XXXX-XXXX-0472', 'ALB0472', '73 Nipani, Bangalore', 'user', 0, 0, 0, '192.168.2.17', 'available', NOW()),
('BS100473', 'Alby Kumar', 'alby.kumar.BS100473@banksploit.local', '+919900000473', '$2y$10$alby0001', MD5('BS100473B4rc@123'), 'XXXX-XXXX-0473', 'ALB0473', '74 Bailhongal, Bangalore', 'user', 0, 0, 0, '192.168.2.18', 'available', NOW()),
('BS100474', 'Alca Singh', 'alca.singh.BS100474@banksploit.local', '+919900000474', '$2y$10$alca0001', MD5('BS100474B4rc@123'), 'XXXX-XXXX-0474', 'ALC0474', '75 Athani, Bangalore', 'user', 0, 0, 0, '192.168.2.19', 'available', NOW()),
('BS100475', 'Alcade Verma', 'alcade.verma.BS100475@banksploit.local', '+919900000475', '$2y$10$alcade01', MD5('BS100475B4rc@123'), 'XXXX-XXXX-0475', 'ALC0475', '76 Soundatti, Bangalore', 'user', 0, 0, 0, '192.168.2.20', 'available', NOW());

-- Continue with batch 8 (BS100476-BS100485)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100476', 'Alcaeus Khan', 'alcaeus.khan.BS100476@banksploit.local', '+919900000476', '$2y$10$alcaeus01', MD5('BS100476B4rc@123'), 'XXXX-XXXX-0476', 'ALC0476', '77 Hiremath, Bangalore', 'user', 0, 0, 0, '192.168.2.21', 'available', NOW()),
('BS100477', 'Alcandra Desai', 'alcandra.desai.BS100477@banksploit.local', '+919900000477', '$2y$10$alcandra1', MD5('BS100477B4rc@123'), 'XXXX-XXXX-0477', 'ALC0477', '78 Saundatti, Bangalore', 'user', 0, 0, 0, '192.168.2.22', 'available', NOW()),
('BS100478', 'Alcantara Iyer', 'alcantara.iyer.BS100478@banksploit.local', '+919900000478', '$2y$10$alcantara', MD5('BS100478B4rc@123'), 'XXXX-XXXX-0478', 'ALC0478', '79 Kittur, Bangalore', 'user', 0, 0, 0, '192.168.2.23', 'available', NOW()),
('BS100479', 'Alcanzar Patel', 'alcanzar.patel.BS100479@banksploit.local', '+919900000479', '$2y$10$alcanzar1', MD5('BS100479B4rc@123'), 'XXXX-XXXX-0479', 'ALC0479', '80 Bailhongal, Bangalore', 'user', 0, 0, 0, '192.168.2.24', 'available', NOW()),
('BS100480', 'Alcar Kumar', 'alcar.kumar.BS100480@banksploit.local', '+919900000480', '$2y$10$alcar0001', MD5('BS100480B4rc@123'), 'XXXX-XXXX-0480', 'ALC0480', '81 Londa, Bangalore', 'user', 0, 0, 0, '192.168.2.25', 'available', NOW()),
('BS100481', 'Alcara Singh', 'alcara.singh.BS100481@banksploit.local', '+919900000481', '$2y$10$alcara01', MD5('BS100481B4rc@123'), 'XXXX-XXXX-0481', 'ALC0481', '82 Hundsund, Bangalore', 'user', 0, 0, 0, '192.168.2.26', 'available', NOW()),
('BS100482', 'Alcaraz Verma', 'alcaraz.verma.BS100482@banksploit.local', '+919900000482', '$2y$10$alcaraz01', MD5('BS100482B4rc@123'), 'XXXX-XXXX-0482', 'ALC0482', '83 Undiyal, Bangalore', 'user', 0, 0, 0, '192.168.2.27', 'available', NOW()),
('BS100483', 'Alcarazs Desai', 'alcarazs.desai.BS100483@banksploit.local', '+919900000483', '$2y$10$alcarazs1', MD5('BS100483B4rc@123'), 'XXXX-XXXX-0483', 'ALC0483', '84 Terdal, Bangalore', 'user', 0, 0, 0, '192.168.2.28', 'available', NOW()),
('BS100484', 'Alcarcell Iyer', 'alcarcell.iyer.BS100484@banksploit.local', '+919900000484', '$2y$10$alcarcell', MD5('BS100484B4rc@123'), 'XXXX-XXXX-0484', 'ALC0484', '85 Mundargi, Bangalore', 'user', 0, 0, 0, '192.168.2.29', 'available', NOW()),
('BS100485', 'Alcarceus Khan', 'alcarceus.khan.BS100485@banksploit.local', '+919900000485', '$2y$10$alcarceus', MD5('BS100485B4rc@123'), 'XXXX-XXXX-0485', 'ALC0485', '86 Gadag, Bangalore', 'user', 0, 0, 0, '192.168.2.30', 'available', NOW());

-- Final batch (BS100486-BS100499)
INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts, last_login_ip, replacement_status, created_at)
VALUES
('BS100486', 'Alcarni Patel', 'alcarni.patel.BS100486@banksploit.local', '+919900000486', '$2y$10$alcarni01', MD5('BS100486B4rc@123'), 'XXXX-XXXX-0486', 'ALC0486', '87 Koppal, Bangalore', 'user', 0, 0, 0, '192.168.2.31', 'available', NOW()),
('BS100487', 'Alcarnis Kumar', 'alcarnis.kumar.BS100487@banksploit.local', '+919900000487', '$2y$10$alcarnis1', MD5('BS100487B4rc@123'), 'XXXX-XXXX-0487', 'ALC0487', '88 Kustigi, Bangalore', 'user', 0, 0, 0, '192.168.2.32', 'available', NOW()),
('BS100488', 'Alcarnus Singh', 'alcarnus.singh.BS100488@banksploit.local', '+919900000488', '$2y$10$alcarnus1', MD5('BS100488B4rc@123'), 'XXXX-XXXX-0488', 'ALC0488', '89 Yadgir, Bangalore', 'user', 0, 0, 0, '192.168.2.33', 'available', NOW()),
('BS100489', 'Alcaros Verma', 'alcaros.verma.BS100489@banksploit.local', '+919900000489', '$2y$10$alcaros01', MD5('BS100489B4rc@123'), 'XXXX-XXXX-0489', 'ALC0489', '90 Raichur, Bangalore', 'user', 0, 0, 0, '192.168.2.34', 'available', NOW()),
('BS100490', 'Alcaté Desai', 'alcate.desai.BS100490@banksploit.local', '+919900000490', '$2y$10$alcate01', MD5('BS100490B4rc@123'), 'XXXX-XXXX-0490', 'ALC0490', '91 Sindhanur, Bangalore', 'user', 0, 0, 0, '192.168.2.35', 'available', NOW()),
('BS100491', 'Alcatena Iyer', 'alcatena.iyer.BS100491@banksploit.local', '+919900000491', '$2y$10$alcatena1', MD5('BS100491B4rc@123'), 'XXXX-XXXX-0491', 'ALC0491', '92 Manvi, Bangalore', 'user', 0, 0, 0, '192.168.2.36', 'available', NOW()),
('BS100492', 'Alcatena Khan', 'alcatena.khan.BS100492@banksploit.local', '+919900000492', '$2y$10$alcatena2', MD5('BS100492B4rc@123'), 'XXXX-XXXX-0492', 'ALC0492', '93 Talikota, Bangalore', 'user', 0, 0, 0, '192.168.2.37', 'available', NOW()),
('BS100493', 'Alcatenas Patel', 'alcatenas.patel.BS100493@banksploit.local', '+919900000493', '$2y$10$alcatenas', MD5('BS100493B4rc@123'), 'XXXX-XXXX-0493', 'ALC0493', '94 Londa, Bangalore', 'user', 0, 0, 0, '192.168.2.38', 'available', NOW()),
('BS100494', 'Alcatenas Kumar', 'alcatenas.kumar.BS100494@banksploit.local', '+919900000494', '$2y$10$alcatenas', MD5('BS100494B4rc@123'), 'XXXX-XXXX-0494', 'ALC0494', '95 Hanagal, Bangalore', 'user', 0, 0, 0, '192.168.2.39', 'available', NOW()),
('BS100495', 'Alcatel Singh', 'alcatel.singh.BS100495@banksploit.local', '+919900000495', '$2y$10$alcatel01', MD5('BS100495B4rc@123'), 'XXXX-XXXX-0495', 'ALC0495', '96 Soundatti, Bangalore', 'user', 0, 0, 0, '192.168.2.40', 'available', NOW()),
('BS100496', 'Alcates Verma', 'alcates.verma.BS100496@banksploit.local', '+919900000496', '$2y$10$alcates01', MD5('BS100496B4rc@123'), 'XXXX-XXXX-0496', 'ALC0496', '97 Bailhongal, Bangalore', 'user', 0, 0, 0, '192.168.2.41', 'available', NOW()),
('BS100497', 'Alcateia Desai', 'alcateia.desai.BS100497@banksploit.local', '+919900000497', '$2y$10$alcateia1', MD5('BS100497B4rc@123'), 'XXXX-XXXX-0497', 'ALC0497', '98 Hiremath, Bangalore', 'user', 0, 0, 0, '192.168.2.42', 'available', NOW()),
('BS100498', 'Alcateis Iyer', 'alcateis.iyer.BS100498@banksploit.local', '+919900000498', '$2y$10$alcateis1', MD5('BS100498B4rc@123'), 'XXXX-XXXX-0498', 'ALC0498', '99 Mundagod, Bangalore', 'user', 0, 0, 0, '192.168.2.43', 'available', NOW()),
('BS100499', 'Alcatey Patel', 'alcatey.patel.BS100499@banksploit.local', '+919900000499', '$2y$10$alcatey01', MD5('BS100499B4rc@123'), 'XXXX-XXXX-0499', 'ALC0499', '100 Dandeli, Bangalore', 'user', 0, 0, 0, '192.168.2.44', 'available', NOW());

-- NOTE: To add all 100 users efficiently, use the following approach:
-- 1. Run .\start-app.ps1 setup  (which now includes all migrations)
-- 2. The migration system will handle creating appropriate accounts for all users

-- For immediate setup of accounts and cards for existing BS1004xx users:

-- Bank Accounts (2 per user)
INSERT IGNORE INTO accounts (user_id, account_type, account_number, balance, daily_limit, is_active)
SELECT u.id, 'savings', CONCAT('BSPL', LPAD(u.id, 12, '0')), ROUND(50000 + RAND() * 450000, 2), 200000, 1
FROM users u WHERE u.customer_id LIKE 'BS1004%' AND NOT EXISTS (SELECT 1 FROM accounts WHERE user_id = u.id AND account_type = 'savings');

INSERT IGNORE INTO accounts (user_id, account_type, account_number, balance, daily_limit, is_active)
SELECT u.id, 'current', CONCAT('BSPL', LPAD(u.id + 1000000, 12, '0')), ROUND(20000 + RAND() * 180000, 2), 200000, 1
FROM users u WHERE u.customer_id LIKE 'BS1004%' AND (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 1;

-- Cards (2 per user)
INSERT IGNORE INTO cards (user_id, card_type, card_number_encrypted, cvv_encrypted, expiry_date, spending_limit, is_blocked)
SELECT u.id, 'debit', CONCAT('4532', LPAD(FLOOR(RAND() * 1000000000000), 12, '0')), LPAD(FLOOR(RAND() * 1000), 3, '0'), CONCAT(LPAD(FLOOR(RAND() * 12) + 1, 2, '0'), '/', YEAR(NOW()) + FLOOR(RAND() * 5)), 100000, 0
FROM users u WHERE u.customer_id LIKE 'BS1004%' AND NOT EXISTS (SELECT 1 FROM cards WHERE user_id = u.id AND card_type = 'debit');

INSERT IGNORE INTO cards (user_id, card_type, card_number_encrypted, cvv_encrypted, expiry_date, spending_limit, is_blocked)
SELECT u.id, 'credit', CONCAT('5412', LPAD(FLOOR(RAND() * 1000000000000), 12, '0')), LPAD(FLOOR(RAND() * 1000), 3, '0'), CONCAT(LPAD(FLOOR(RAND() * 12) + 1, 2, '0'), '/', YEAR(NOW()) + FLOOR(RAND() * 5)), 500000, 0
FROM users u WHERE u.customer_id LIKE 'BS1004%' AND (SELECT COUNT(*) FROM cards WHERE user_id = u.id) = 1;

-- Show verification
SELECT 'Users Added (BS1004xx)' as metric, COUNT(*) as count FROM users WHERE customer_id LIKE 'BS1004%'
UNION ALL SELECT 'Total Users', COUNT(*) FROM users
UNION ALL SELECT 'Bank Accounts', COUNT(*) FROM accounts a INNER JOIN users u ON a.user_id = u.id WHERE u.customer_id LIKE 'BS1004%'
UNION ALL SELECT 'Cards', COUNT(*) FROM cards c INNER JOIN users u ON c.user_id = u.id WHERE u.customer_id LIKE 'BS1004%';
