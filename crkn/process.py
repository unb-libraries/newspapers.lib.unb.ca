import os
import re
import shutil
import sys
import xml.etree.ElementTree as ET

from pathlib import Path

def check_metadata_structure_assertions(path):
    if path == "":
        print('Input path not specified.')
        sys.exit(1)
    cmr_file_paths = find_in_path('cmr.xml', sys.argv[1])
    for cmr_file_path in cmr_file_paths:
        cmr_dir = os.path.dirname(cmr_file_path)
        metadata_file_path = os.path.join(cmr_dir, 'sip/data/metadata.xml')
        if not os.path.isfile(metadata_file_path):
            print('No metadata.xml file found for CMR file: ' + cmr_file_path)
            sys.exit(1)
    return path

def check_output_path(path):
    if path == "":
        print('Output path not specified.')
        sys.exit(1)
    if not os.path.isdir(path):
        print('Output path does not exist: ' + path)
        sys.exit(1)
    return path

def clean_title_for_path(title):
    title = title.split(':')[0].strip()
    title = re.sub(r"\[.*\]", "", title).strip()
    title = re.sub('[^0-9a-zA-Z]+', ' ', title).strip()
    title = re.sub('\s\s+', ' ', title).strip()
    title = title.replace(' ', '_').title()
    return title

def find_images_in_path(path):
    result = []
    for root, dirs, files in os.walk(path):
        for file_found in files:
            if file_found.lower().endswith((
                '.png',
                '.tif',
                '.tiff',
                '.jpeg',
                '.jpg',
                '.jp2'
                )):
                result.append(os.path.join(root, file_found))
    return result

def find_in_path(name, path):
    result = []
    for root, dirs, files in os.walk(path):
        if name in files:
            result.append(os.path.join(root, name))
    return result

def generate_issue_path(metadata_file_path, issue_metadata, output_path):
    cleaned_title=clean_title_for_path(issue_metadata['title'])
    series =  issue_metadata['series']
    sequence =  issue_metadata['sequence']
    year = issue_metadata['published']
    return os.path.join(output_path, cleaned_title, year, series, sequence)

def get_metadata_file_marker_path(metadata_file_path, output_path):
    return os.path.join(
        output_path,
        '.crkn_processed',
        metadata_file_path.replace('.xml', '.xml.processed').strip("/")
    )

def mark_metadata_file_as_processed(metadata_file_path, output_path):
    marker_file_path = get_metadata_file_marker_path(metadata_file_path, output_path)
    Path(os.path.dirname(marker_file_path)).mkdir(parents=True, exist_ok=True)
    Path(marker_file_path).touch()

def metadata_file_needs_processing(metadata_file_path, output_path):
    marker_file_path = get_metadata_file_marker_path(metadata_file_path, output_path)
    if os.path.isfile(marker_file_path):
        return False
    return True

def normalize_path(path):
    return os.path.normpath(os.path.abspath(path))

## Main
source_path = normalize_path(
    check_metadata_structure_assertions(sys.argv[1])
)
output_path = normalize_path(
    check_output_path(sys.argv[2])
)

for metadata_file_path in find_in_path('metadata.xml', source_path):
    if not metadata_file_needs_processing(metadata_file_path, output_path):
        print("Skipping: " + metadata_file_path + "...")
        continue
    print("Processing: " + metadata_file_path + "...")
    tree = ET.parse(metadata_file_path)
    root = tree.getroot()
    for item in root.findall('.//{http://canadiana.ca/schema/2012/xsd/issueinfo}issueinfo'):
        issue_metadata = {}
        for metadata_item in item:
            issue_metadata[
                metadata_item.tag.replace(
                    '{http://canadiana.ca/schema/2012/xsd/issueinfo}',
                    ''
                )
            ] = metadata_item.text
        issue_path = generate_issue_path(metadata_file_path, issue_metadata, output_path)
        Path(issue_path).mkdir(parents=True, exist_ok=True)
        copy_source = metadata_file_path.replace('/data/sip/data/metadata.xml','')
        for image in find_images_in_path(copy_source):
            shutil.copy2(image, issue_path)
            shutil.copy2(metadata_file_path, issue_path)
            cmr_file_path = os.path.join(copy_source, 'data/cmr.xml')
            if os.path.isfile(cmr_file_path):
                shutil.copy2(cmr_file_path, issue_path)
    mark_metadata_file_as_processed(metadata_file_path, output_path)
