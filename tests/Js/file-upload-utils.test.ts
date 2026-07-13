import { describe, it, expect } from 'vitest'
import { formatSize, getFileIcon } from '../../src/assets/js/utils/file-upload'

describe('formatSize', () => {
  it('formats bytes correctly', () => {
    expect(formatSize(0)).toBe('0 B')
    expect(formatSize(500)).toBe('500 B')
    expect(formatSize(1023)).toBe('1023 B')
  })

  it('formats kilobytes correctly', () => {
    expect(formatSize(1024)).toBe('1 KB')
    expect(formatSize(1536)).toBe('2 KB')
    expect(formatSize(10240)).toBe('10 KB')
  })

  it('formats megabytes correctly', () => {
    expect(formatSize(1048576)).toBe('1,00 MB')
    expect(formatSize(2097152)).toBe('2,00 MB')
    expect(formatSize(5242880)).toBe('5,00 MB')
  })

  it('formats large files correctly', () => {
    expect(formatSize(10485760)).toBe('10,00 MB')
    expect(formatSize(104857600)).toBe('100,00 MB')
  })
})

describe('getFileIcon', () => {
  it('returns fa-file for null', () => {
    expect(getFileIcon(null)).toBe('fa-file')
  })

  it('returns fa-file-image for image types', () => {
    expect(getFileIcon('image/jpeg')).toBe('fa-file-image')
    expect(getFileIcon('image/png')).toBe('fa-file-image')
    expect(getFileIcon('image/gif')).toBe('fa-file-image')
  })

  it('returns fa-file-pdf for PDF', () => {
    expect(getFileIcon('application/pdf')).toBe('fa-file-pdf')
  })

  it('returns fa-file-word for Word documents', () => {
    expect(getFileIcon('application/msword')).toBe('fa-file-word')
    expect(getFileIcon('application/vnd.oasis.opendocument.text')).toBe('fa-file-word')
  })

  it('returns fa-file-excel for plain Excel MIME', () => {
    expect(getFileIcon('application/vnd.ms-excel')).toBe('fa-file-excel')
  })

  it('returns fa-file for unknown types', () => {
    expect(getFileIcon('text/plain')).toBe('fa-file')
    expect(getFileIcon('application/json')).toBe('fa-file')
    expect(getFileIcon('video/mp4')).toBe('fa-file')
  })
})
