# FreshRSS Notion Extension

A FreshRSS extension that adds YouTube videos to your Notion database with automatic metadata extraction.

## Features

- **YouTube Integration**: Automatically detects YouTube videos in your FreshRSS feed
- **Smart Detection**: Only works with full videos (`/watch` URLs), excludes Shorts (`/shorts`)
- **Rich Metadata**: Extracts video title, channel name, and upload date
- **Duplicate Prevention**: Prevents adding the same video twice
- **Auto Mark Read**: Marks FreshRSS items as read when successfully added to Notion
- **Configurable Properties**: Map video data to your existing Notion database properties
- **Additional Properties**: Set fixed properties like Type=Video, Platform=YouTube
- **Error Handling**: Shows raw Notion API errors for troubleshooting
- **YouTube API Support**: Optional YouTube Data API integration for better metadata

## Quick Start

1. Install the extension in your FreshRSS extensions directory
2. Create a Notion integration and get your API key
3. Set up a Notion database with URL, Name, Author, and Date properties  
4. Configure the extension with your Notion credentials
5. Add the integration to your Notion database
6. Subscribe to YouTube channels in FreshRSS
7. Click "Add to Notion" on any YouTube video

## Requirements

- FreshRSS with extension support
- Notion account with integration capabilities
- YouTube Data API v3 key (optional but recommended)

## Installation

See [INSTALL.md](INSTALL.md) for detailed setup instructions.

## Configuration

The extension supports:
- Notion API key and database ID
- YouTube API key (optional)
- Custom property name mappings
- Additional fixed properties (JSON or simple format)

## Usage

1. Open any YouTube video in FreshRSS
2. Click the "Add to Notion" button
3. Watch the loading indicator
4. Success: Video added to Notion, article marked as read
5. Error: Raw error message displayed, retry available

## Database Schema

Your Notion database should have these properties:

| Property | Type | Description | Required |
|----------|------|-------------|----------|
| Url | URL | Video URL | Yes |
| Name | Title | Video title | Yes |
| Author | Multi-select | Channel name | Yes |
| Date | Date | Upload date | Yes |

Additional properties can be configured as needed.

## Error Handling

The extension shows raw Notion API errors to help with troubleshooting:
- Missing configuration
- Database access issues  
- Duplicate entries
- Invalid property mappings
- API rate limits

## Development

Built following FreshRSS extension guidelines:
- PHP backend with Notion REST API integration
- JavaScript frontend with loading states
- CSS styling consistent with FreshRSS
- CSRF protection and proper error handling

## License

MIT License - see [LICENSE](LICENSE) for details.
