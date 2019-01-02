USE oai_repo

INSERT IGNORE INTO `oai_repo` (`id`, `repositoryName`, `baseURL`, `protocolVersion`, `adminEmails`, `earliestDatestamp`, `deletedRecord`, `granularity`, `maxListSize`, `tokenDuration`, `updated`, `comment`) VALUES
('demo', 'Library of Congress Open Archive Initiative\r\nRepository 1', 'http://memory.loc.gov/cgi-bin/oai', '2.0', 'somebody@loc.gov, anybody@loc.gov', '1990-02-01T12:00:00Z', 'transient', 'YYYY-MM-DDThh:mm:ssZ', 100, 3600, '2017-08-11 19:42:45', 'Demo repository');

INSERT IGNORE INTO `oai_repo_description` (`repo`, `description`, `rank`, `updated`, `comment`) VALUES
('demo', '<oai-identifier xmlns=\"http://www.openarchives.org/OAI/2.0/oai-identifier\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd\"><scheme>oai</scheme><repositoryIdentifier>lcoa1.loc.gov</repositoryIdentifier><delimiter>:</delimiter><sampleIdentifier>oai:lcoa1.loc.gov:loc.music/musdi.002</sampleIdentifier></oai-identifier>', 1, '2017-08-11 09:21:58', 'identifier'),
('demo', '<friends xmlns=\"http://www.openarchives.org/OAI/2.0/friends/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/friends/ http://www.openarchives.org/OAI/2.0/friends.xsd\"> <baseURL>http://oai.east.org/foo/</baseURL><baseURL>http://oai.hq.org/bar/</baseURL><baseURL>http://oai.south.org/repo.cgi</baseURL></friends>', 3, '2017-08-11 09:21:58', 'friends'),
('demo', '<eprints xmlns=\"http://www.openarchives.org/OAI/1.1/eprints\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/1.1/eprints http://www.openarchives.org/OAI/1.1/eprints.xsd\"><content><URL>http://memory.loc.gov/ammem/oamh/lcoa1_content.html</URL><text>Selected collections from American Memory at the Library of Congress</text></content><metadataPolicy/><dataPolicy/></eprints>', 2, '2017-08-11 09:21:58', 'eprints');

INSERT IGNORE INTO `oai_meta` (`repo`, `metadataPrefix`, `schema`, `metadataNamespace`, `updated`, `comment`) VALUES
('demo', 'oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'http://www.openarchives.org/OAI/2.0/oai_dc/', '2017-08-11 09:13:43', 'oai_dc support is required by the OAI protocol. Do not change or remove.');

INSERT IGNORE INTO `oai_set` (`repo`, `setSpec`, `setName`, `setDescription`, `rank`, `updated`, `comment`) VALUES
('demo', 'music', 'Music collection', NULL, 0, '2017-08-11 09:30:24', NULL),
('demo', 'video', 'Video Collection', NULL, 3, '2017-08-11 09:30:24', NULL),
('demo', 'music:(elec)', 'Electronic Music Collection', '<oai_dc:dc xmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd\"><dc:description>This set contains metadata describing electronic music recordings made during the 1950ies</dc:description></oai_dc:dc>', 2, '2017-08-11 09:33:22', NULL),
('demo', 'music:(muzak)', 'Muzak collection', NULL, 1, '2017-08-11 09:30:24', NULL);

INSERT IGNORE INTO `oai_item_meta` (`repo`, `history`, `serial`, `identifier`, `metadataPrefix`, `datestamp`, `deleted`, `metadata`, `created`, `updated`) VALUES
('demo', 0, 0, 'oai:lcoa1.loc.gov:loc.music/musdi.002', 'oai_dc', '2016-10-24 08:13:45', 0, '<?xml version=\"1.0\"?>\r\n<oai_dc:dc xmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd\"><dc:title>Opera Minora</dc:title><dc:creator>Cornelius Tacitus</dc:creator><dc:type>text</dc:type><dc:source>Opera Minora. Cornelius Tacitus. Henry Furneaux. Clarendon Press. Oxford. 1900.</dc:source><dc:language>latin</dc:language><dc:identifier>http://www.perseus.tufts.edu/cgi-bin/ptext?doc=Perseus:text:1999.02.0084</dc:identifier></oai_dc:dc>', '2016-10-20 16:49:28', '2017-08-11 09:43:24');

INSERT IGNORE INTO `oai_item_set` (`repo`, `history`, `serial`, `identifier`, `metadataPrefix`, `setSpec`, `confirmed`, `created`, `updated`) VALUES
('demo', 0, 165495, 'oai:lcoa1.loc.gov:loc.music/musdi.002', 'oai_dc', 'music:(muzak)', 1, '2017-06-29 16:21:46', '2017-08-11 09:45:43');


