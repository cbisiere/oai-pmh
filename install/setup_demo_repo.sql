USE oai_repo

INSERT IGNORE INTO `oai_repo` (`id`, `repositoryName`, `baseURL`, `protocolVersion`, `adminEmails`, `earliestDatestamp`, `deletedRecord`, `granularity`, `maxListSize`, `tokenDuration`, `updated`, `comment`) VALUES
('demo', 'Example Open Archive Initiative Repository', 'https://example.com/oai2', '2.0', 'somebody@example.com, anybody@example.com', '1990-02-01T12:00:00Z', 'transient', 'YYYY-MM-DDThh:mm:ssZ', 100, 3600, '2017-08-11 19:42:45', 'Demo repository');

INSERT IGNORE INTO `oai_repo_description` (`repo`, `description`, `rank`, `updated`, `comment`) VALUES
('demo', '<oai-identifier xmlns=\"http://www.openarchives.org/OAI/2.0/oai-identifier\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd\"><scheme>oai</scheme><repositoryIdentifier>example.com</repositoryIdentifier><delimiter>:</delimiter><sampleIdentifier>oai:example.com:nash1950equilibrium</sampleIdentifier></oai-identifier>', 1, '2022-12-27 19:22:58', 'identifier'),
('demo', '<friends xmlns=\"http://www.openarchives.org/OAI/2.0/friends/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/friends/ http://www.openarchives.org/OAI/2.0/friends.xsd\"> <baseURL>http://oai.openedition.org/</baseURL><baseURL>http://eprints.lse.ac.uk/cgi/oai2</baseURL><baseURL>https://www.repository.cam.ac.uk/oai/request</baseURL></friends>', 2, '2022-12-27 19:22:58', 'friends');

INSERT IGNORE INTO `oai_meta` (`repo`, `metadataPrefix`, `schema`, `metadataNamespace`, `updated`, `comment`) VALUES
('demo', 'oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'http://www.openarchives.org/OAI/2.0/oai_dc/', '2022-12-27 19:22:58', 'oai_dc support is required by the OAI protocol. Do not change or remove.'),
('demo', 'mods', 'http://www.loc.gov/standards/mods/v3/mods-3-8.xsd', 'http://www.loc.gov/mods/v3', '2022-12-27 19:22:58', 'mods 3.8.');

INSERT IGNORE INTO `oai_set` (`repo`, `setSpec`, `setName`, `rank`, `updated`, `comment`) VALUES
('demo', 'pub', 'Scientific collection', 0, '2022-12-27 19:22:58', NULL),
('demo', 'pub:(econ)', 'Economics Collection', 2, '2022-12-27 19:22:58', NULL);

INSERT IGNORE INTO `oai_set_description` (`repo`, `setSpec`, `setDescription`, `rank`, `updated`, `comment`) VALUES
('demo', 'pub:(econ)', '<oai_dc:dc xmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd\"><dc:description>This set contains metadata describing scientific publications in economics published during the 1950ies</dc:description></oai_dc:dc>', 1, '2022-12-27 19:22:58', NULL);

INSERT IGNORE INTO `oai_item_meta` (`repo`, `history`, `serial`, `identifier`, `metadataPrefix`, `datestamp`, `deleted`, `metadata`, `created`, `updated`) VALUES
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'oai_dc', '2022-12-27 19:22:58', 0, '<?xml version="1.0"?>
<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
  <dc:title xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:srw_dc="info:srw/schema/1/dc-schema">Equilibrium points in n-person games</dc:title>
  <dc:contributor xmlns:dc="http://purl.org/dc/elements/1.1/">Nash, J (author)</dc:contributor>
  <dc:date xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:srw_dc="info:srw/schema/1/dc-schema">1950</dc:date>
  <dc:type xmlns:dc="http://purl.org/dc/elements/1.1/">Text</dc:type>
  <dc:type xmlns:dc="http://purl.org/dc/elements/1.1/">journal article</dc:type>
  <dc:relation xmlns:dc="http://purl.org/dc/elements/1.1/">Proceedings of the National Academy of Sciences of the United States of America</dc:relation>
  <dc:identifier xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:srw_dc="info:srw/schema/1/dc-schema">citekey:&#xA0;nash1950equilibrium</dc:identifier>
</oai_dc:dc>
', '2022-12-27 19:22:58', '2022-12-27 19:22:58'),
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'mods', '2022-12-27 19:22:58', 0, '<?xml version="1.0"?>
<mods:mods xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-8.xsd http://www.w3.org/1999/xlink http://www.loc.gov/standards/xlink/xlink.xsd">
	<mods:titleInfo>
        <mods:title>Equilibrium points in n-person games</mods:title>
    </mods:titleInfo><mods:name type="personal">
        <mods:namePart type="given">J</mods:namePart>
        <mods:namePart type="family">Nash</mods:namePart>
        <mods:role>
            <mods:roleTerm authority="marcrelator" type="text">author</mods:roleTerm>
        </mods:role>
    </mods:name><mods:originInfo>
        <mods:dateIssued>1950</mods:dateIssued>
    </mods:originInfo><mods:typeOfResource>text</mods:typeOfResource><mods:genre authority="bibutilsgt">journal article</mods:genre><mods:relatedItem type="host">
        <mods:titleInfo>
            <mods:title>Proceedings of the National Academy of Sciences of the United States of America</mods:title>
        </mods:titleInfo>
        <mods:originInfo>
            <mods:issuance>continuing</mods:issuance>
            <mods:publisher>National Academy of Sciences</mods:publisher>
        </mods:originInfo>
        <mods:genre authority="marcgt">periodical</mods:genre>
        <mods:genre authority="bibutilsgt">academic journal</mods:genre>
    </mods:relatedItem><mods:identifier type="citekey">nash1950equilibrium</mods:identifier><mods:part>
        <mods:date>1950</mods:date>
        <mods:detail type="volume"><mods:number>36</mods:number></mods:detail>
        <mods:detail type="issue"><mods:number>1</mods:number></mods:detail>
        <mods:extent unit="page">
            <mods:start>48</mods:start>
            <mods:end>49</mods:end>
        </mods:extent>
    </mods:part></mods:mods>
', '2022-12-27 19:22:58', '2022-12-27 19:22:58');

INSERT INTO `oai_item_meta_about` (`repo`, `history`, `serial`, `identifier`, `metadataPrefix`, `datestamp`, `about`, `rank`, `created`, `updated`) VALUES
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'oai_dc', '2024-07-25 13:56:06', 
'<?xml version="1.0"?>
<provenance xmlns=\"http://www.openarchives.org/OAI/2.0/provenance\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/provenance http://www.openarchives.org/OAI/2.0/provenance.xsd\">
    <originDescription harvestDate=\"2024-07-25T14:10:02Z\" altered=\"true\">
        <baseURL>http://another.demo.org</baseURL>
        <identifier>oai:another.demo.org:nash1950equilibrium</identifier>
        <datestamp>2002-01-01</datestamp>
        <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>
    </originDescription>
</provenance>
', 1, '2024-07-25 13:56:06', '2024-07-25 14:22:32'),
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'oai_dc', '2024-07-25 14:16:17',
'<?xml version="1.0"?>
<rights xmlns=\"http://www.openarchives.org/OAI/2.0/rights/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/rights/ http://www.openarchives.org/OAI/2.0/rights.xsd\">
    <rightsReference ref=\"http://creativecommons.org/licenses/by-nd/2.0/rdf\"/>
</rights>
', 2, '2024-07-25 14:16:17', '2024-07-25 14:16:56'),
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'mods', '2024-07-25 13:56:06', 
'<?xml version="1.0"?>
<provenance xmlns=\"http://www.openarchives.org/OAI/2.0/provenance\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/provenance http://www.openarchives.org/OAI/2.0/provenance.xsd\">
    <originDescription harvestDate=\"2024-07-25T14:10:02Z\" altered=\"true\">
        <baseURL>http://another.demo.org</baseURL>
        <identifier>oai:another.demo.org:nash1950equilibrium</identifier>
        <datestamp>2002-01-01</datestamp>
        <metadataNamespace>http://www.loc.gov/mods/v3</metadataNamespace>
    </originDescription>
</provenance>
', 1, '2024-07-25 13:56:06', '2024-07-25 14:22:32'),
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'mods', '2024-07-25 14:16:17',
'<?xml version="1.0"?>
<rights xmlns=\"http://www.openarchives.org/OAI/2.0/rights/\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/rights/ http://www.openarchives.org/OAI/2.0/rights.xsd\">
    <rightsReference ref=\"http://creativecommons.org/licenses/by-nd/2.0/rdf\"/>
</rights>
', 2, '2024-07-25 14:16:17', '2024-07-25 14:16:56');

INSERT IGNORE INTO `oai_item_set` (`repo`, `history`, `serial`, `identifier`, `metadataPrefix`, `setSpec`, `confirmed`, `created`, `updated`) VALUES
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'oai_dc', 'pub:(econ)', 1, '2022-12-27 19:22:58', '2022-12-27 19:22:58'),
('demo', 0, 0, 'oai:demo.org:nash1950equilibrium', 'mods', 'pub:(econ)', 1, '2022-12-27 19:22:58', '2022-12-27 19:22:58');

