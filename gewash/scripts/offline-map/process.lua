node_keys = {}
way_keys = { "highway", "natural", "waterway", "water" }

local drive = {
	motorway = 6,
	trunk = 6,
	motorway_link = 9,
	trunk_link = 9,
	primary = 8,
	primary_link = 10,
	secondary = 10,
	secondary_link = 12,
	tertiary = 10,
	tertiary_link = 12,
	unclassified = 11,
	residential = 11,
	living_street = 12,
	service = 13
}

function node_function()
end

function way_function()
	local highway = Find("highway")
	local zoom = drive[highway]
	if zoom ~= nil then
		Layer("roads", false)
		Attribute("class", highway)
		MinZoom(zoom)
		local name = Find("name")
		if name ~= "" then
			Layer("road_name", false)
			Attribute("name", name)
			Attribute("class", highway)
			MinZoom(math.max(zoom, 13))
		end
		return
	end

	local natural = Find("natural")
	local water = Find("water")
	local waterway = Find("waterway")
	if natural == "water" or water ~= "" or waterway == "riverbank" then
		Layer("water", true)
		MinZoom(6)
		return
	end

	if waterway == "river" or waterway == "canal" then
		Layer("waterway", false)
		MinZoom(9)
	end
end
