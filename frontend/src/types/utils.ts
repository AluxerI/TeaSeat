export type typePic = 'svg' | 'jpeg' | 'png';

export type Picture = {
  name: string;
  alt: string;
  type: typePic;
};
export type Json = {[key:string]: unknown}&Record<string,unknown>;