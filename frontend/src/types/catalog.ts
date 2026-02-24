export type Rating = {
    countUser5:number;
    countUser4:number;
    countUser3:number;
    countUser2:number;
    countUser1:number;
}


export class Discount {
    private constructor(private readonly value:number=0){}
    static form(value:number):Discount{
        if (value < 0 || value > 100) {
      throw new Error('Процент должен быть в диапазоне 0-100');
      
    }
    return new Discount(value);
    }
}